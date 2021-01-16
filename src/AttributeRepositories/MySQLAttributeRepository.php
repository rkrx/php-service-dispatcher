<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use Closure;
use DateTimeInterface;
use PDO;
use PDOException;
use PDOStatement;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use RuntimeException;
use Throwable;

class MySQLAttributeRepository implements AttributeRepository {
	const MYSQL_ERR_TABLE_MSSING = '42S02';
	const MYSQL_ERR_TABLE_COLUMN_MSSING = '42S22';
	
	/** @var PDOStatement */
	private $selectOverdueServices = null;
	/** @var PDOStatement */
	private $registerRow = null;
	/** @var PDOStatement */
	private $hasService = null;
	/** @var PDOStatement */
	private $getServiceKeys = null;
	/** @var PDOStatement */
	private $getData = null;
	/** @var PDOStatement */
	private $updateTryDate = null;
	/** @var PDOStatement */
	private $updateRunDate = null;
	/** @var PDOStatement */
	private $updateNextDate = null;
	/** @var PDOStatement */
	private $lock;
	/** @var PDOStatement */
	private $unlock;
	/** @var PDO */
	private $pdo;
	/** @var string[]|null */
	private $services = null;
	/** @var string */
	private $tableName;
	/** @var array */
	private $options;
	
	/**
	 * @param PDO $pdo
	 * @param string $tableName
	 * @param array $options
	 */
	public function __construct(PDO $pdo, $tableName = 'services', array $options = []) {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->tableName = $tableName;
		$this->options = $options;
		$this->pdo = $pdo;
		if($options['use-locking'] ?? true) {
			$this->lock = $pdo->prepare('SELECT GET_LOCK(:name, 0)');
			$this->unlock = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
		}
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function has(string $key) {
		if($this->hasService === null) {
			$this->hasService = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->tableName}` WHERE service_key=:key;");
		}
		$this->hasService->execute(['key' => $key]);
		$count = $this->hasService->fetchColumn(0);
		return $count > 0;
	}
	
	/**
	 * @param string $key
	 * @return MySQLAttributeRepository|void
	 */
	public function register(string $key) {
		$this->handleException(function () use ($key) {
			if($this->services === null) {
				if($this->getServiceKeys === null) {
					$this->getServiceKeys = $this->pdo->prepare("SELECT `service_key` FROM `{$this->tableName}`;");
				}
				try {
					$this->getServiceKeys->execute();
					$this->services = $this->getServiceKeys->fetchAll(PDO::FETCH_COLUMN);
				} finally {
					$this->getServiceKeys->closeCursor();
				}
			}
			if(!in_array($key, $this->services, true)) {
				if($this->registerRow === null) {
					$this->registerRow = $this->pdo->prepare("INSERT INTO `{$this->tableName}` (`service_key`) VALUES (:key)");
				}
				$this->registerRow->execute(['key' => $key]);
			}
		});
	}

	/**
	 * @param string $key
	 * @return object
	 */
	public function getRowByKey(string $key) {
		if($this->getData === null) {
			$this->getData = $this->pdo->prepare("SELECT service_key, service_last_try, service_last_run, service_next_run FROM `{$this->tableName}` WHERE service_key=:key;");
		}
		try {
			$this->handleException(function () use ($key) {
				$this->getData->execute(['key' => $key]);
			});
			$result = $this->getData->fetchObject();
			if(!is_object($result)) {
				throw new RuntimeException('Row not found');
			}
			return $result;
		} finally {
			$this->getData->closeCursor();
		}
	}
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return MySQLAttributeRepository|void
	 */
	public function setLastTryDate(string $key, DateTimeInterface $datetime) {
		if($this->updateTryDate === null) {
			$this->updateTryDate = $this->pdo->prepare("INSERT INTO `{$this->tableName}` (service_key, service_last_try) VALUES (:key, :dt) ON DUPLICATE KEY UPDATE service_last_try=:dt");
		}
		$this->handleException(function () use ($key, $datetime) {
			$this->updateTryDate->execute(['key' => $key, 'dt' => $datetime->format('Y-m-d H:i:s')]);
		});
	}
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return MySQLAttributeRepository|void
	 */
	public function setLastRunDate(string $key, DateTimeInterface $datetime) {
		if($this->updateRunDate === null) {
			$this->updateRunDate = $this->pdo->prepare("INSERT INTO `{$this->tableName}` (service_key, service_last_run) VALUES (:key, :dt) ON DUPLICATE KEY UPDATE service_last_run=:dt");
		}
		$this->handleException(function () use ($key, $datetime) {
			$this->updateRunDate->execute(['key' => $key, 'dt' => $datetime->format('Y-m-d H:i:s')]);
		});
	}
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return MySQLAttributeRepository|void
	 */
	public function setNextRunDate(string $key, DateTimeInterface $datetime) {
		if($this->updateNextDate === null) {
			$this->updateNextDate = $this->pdo->prepare("INSERT INTO `{$this->tableName}` (service_key, service_next_run) VALUES (:key, :dt) ON DUPLICATE KEY UPDATE service_next_run=:dt");
		}
		$this->handleException(function () use ($key, $datetime) {
			$this->updateNextDate->execute(['key' => $key, 'dt' => $datetime->format('Y-m-d H:i:s')]);
		});
	}
	
	/**
	 * @param DateTimeInterface|null $now
	 * @param callable $fn
	 * @return int
	 * @throws Throwable
	 */
	public function lockAndIterateServices(?DateTimeInterface $now, callable $fn): int {
		$data = (object) ['count' => 0];
			$services = $this->fetchServices($now);
			foreach($services as $service) {
				try {
					$this->lock($service->getKey());
					$fn($service);
					$data->count++;
				} finally {
					$this->unlock($service->getKey());
				}
			}
			return $data->count;
	}
	
	/**
	 * @param DateTimeInterface $now
	 * @return Service[]
	 */
	private function fetchServices(DateTimeInterface $now) {
		if($this->selectOverdueServices === null) {
			$this->selectOverdueServices = $this->pdo->prepare("SELECT service_key, service_last_try, service_last_run, service_next_run FROM `{$this->tableName}` WHERE IFNULL(service_next_run, DATE('2000-01-01')) <= :dt;");
		}
		return $this->handleException(function () use ($now) {
			$this->selectOverdueServices->execute(['dt' => $now->format('Y-m-d H:i:d')]);
			try {
				$services = $this->selectOverdueServices->fetchAll(PDO::FETCH_ASSOC);
				$result = [];
				foreach($services as $service) {
					$result[] = new Service($service['service_key']);
				}
				return $result;
			} finally {
				$this->selectOverdueServices->closeCursor();
			}
		});
	}
	
	/**
	 * Is the lock can not be optained, an RuntimeException is thrown
	 *
	 * @param string $key
	 */
	public function lock(string $key): void {
		try {
			$lockName = sprintf('%s%s', $this->options['lock-prefix'] ?? '', $key);
			$this->lock->execute(['name' => $lockName]);
			$lockObtained = $this->lock->fetchColumn(0);
			if(!$lockObtained) {
				throw new RuntimeException(sprintf('Could not obtain lock "%s"', $lockName));
			}
		} finally {
			$this->lock->closeCursor();
		}
	}
	
	/**
	 * @param string $key
	 */
	public function unlock(string $key): void {
		$this->unlock->execute(['name' => sprintf('%s%s', $this->options['lock-prefix'] ?? '', $key)]);
	}
	
	/**
	 * @param Closure $fn
	 * @return mixed
	 */
	private function handleException(Closure $fn) {
		try {
			return $fn();
		} catch (PDOException $e) {
			if($e->getCode() === self::MYSQL_ERR_TABLE_MSSING) {
				// Field is missing, let's have a look what is going on...
				$this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (`service_key` VARCHAR(255) NOT NULL DEFAULT '', `service_last_try` DATETIME NULL DEFAULT NULL, `service_last_run` DATETIME NULL DEFAULT NULL, `service_next_run` DATETIME NULL DEFAULT NULL, PRIMARY KEY (`service_key`));");
				return $this->retry($fn);
			}
			
			if($e->getCode() === self::MYSQL_ERR_TABLE_COLUMN_MSSING) {
				// Field is missing, let's have a look what is going on...
				$this->checkIfOldTableVersion();
				return $this->retry($fn);
			}
			
			throw $e;
		}
	}
	
	/**
	 * @param Closure $fn
	 * @return mixed
	 */
	private function retry(Closure $fn) {
		return $this->handleException(function () use ($fn) {
			return $fn();
		});
	}
	
	/**
	 */
	private function checkIfOldTableVersion() {
		$serviceNextRunField = $this->pdo->query("SHOW COLUMNS FROM `{$this->tableName}` LIKE 'service_next_run';")->fetchAll(PDO::FETCH_ASSOC);

		if(!count($serviceNextRunField)) {
			$this->pdo->exec("ALTER TABLE `{$this->tableName}` ADD COLUMN `service_next_run` DATETIME NULL DEFAULT NULL AFTER `service_last_run`;");
			$serviceTimeoutField = $this->pdo->query("SHOW COLUMNS FROM `{$this->tableName}` LIKE 'service_timeout';")->fetchAll(PDO::FETCH_ASSOC);
			if(count($serviceTimeoutField)) {
				$this->pdo->exec("UPDATE `{$this->tableName}` SET service_next_run = DATE_ADD(service_last_run, INTERVAL service_timeout SECOND)");
				$this->pdo->exec("ALTER TABLE `{$this->tableName}` DROP COLUMN `service_timeout`;");
			}
		}
	}
}
