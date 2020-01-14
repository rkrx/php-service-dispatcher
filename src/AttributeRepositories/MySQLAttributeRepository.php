<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use Generator;
use PDO;
use PDOStatement;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use RuntimeException;
use Throwable;

class MySQLAttributeRepository implements AttributeRepository {
	/** @var PDOStatement */
	private $selectServices;
	/** @var PDOStatement */
	private $hasService;
	/** @var PDOStatement */
	private $insertService;
	/** @var PDOStatement */
	private $updateService;
	/** @var PDOStatement */
	private $updateTryDate;
	/** @var PDOStatement */
	private $updateRunDate;
	/** @var array */
	private $services = [];
	/** @var PDO */
	private $pdo;
	
	/**
	 * @param PDO $pdo
	 * @param string $tableName
	 * @param array $options
	 */
	public function __construct(PDO $pdo, $tableName = 'services', array $options = []) {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec("CREATE TABLE IF NOT EXISTS `{$tableName}` (`service_key` VARCHAR(255) NOT NULL DEFAULT '', `service_last_try` DATETIME NULL DEFAULT '2000-01-01 00:00:00', `service_last_run` DATETIME NULL DEFAULT '2000-01-01 00:00:00', `service_timeout` INT UNSIGNED NULL DEFAULT '0', PRIMARY KEY (`service_key`));");
		
		$skipLocked = '';
		if($options['skip-locked'] ?? false) {
			$skipLocked = ' SKIP LOCKED';
		}
		$this->selectServices = $pdo->prepare("SELECT service_key FROM `{$tableName}` WHERE DATE_ADD(service_last_run, INTERVAL service_timeout SECOND) <= NOW() ORDER BY GREATEST(service_last_try, service_last_run) ASC FOR UPDATE{$skipLocked};");
		
		$this->hasService = $pdo->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE service_key=:key;");
		$this->insertService = $pdo->prepare("INSERT INTO `{$tableName}` (service_key, service_last_try, service_last_run, service_timeout) VALUES (:key, :try, :run, :timeout);");
		$this->updateService = $pdo->prepare("UPDATE `{$tableName}` SET service_timeout=:timeout WHERE service_key=:key;");
		$this->updateTryDate = $pdo->prepare("UPDATE `{$tableName}` SET service_last_try=NOW() WHERE service_key=:key;");
		$this->updateRunDate = $pdo->prepare("UPDATE `{$tableName}` SET service_last_run=NOW() WHERE service_key=:key;");
		$this->pdo = $pdo;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function has($key) {
		$this->hasService->execute(['key' => $key]);
		$count = $this->hasService->fetchColumn(0);
		return $count > 0;
	}

	/**
	 * @param string $key
	 * @param int $timeout
	 * @param array $data
	 * @return $this
	 */
	public function store($key, $timeout, array $data = []) {
		$key = trim(strtolower($key));
		if(!in_array($key, $this->services)) {
			$this->services[] = $key;
		} else {
			throw new RuntimeException("Duplicate service: {$key}");
		}

		if($this->has($key)) {
			$this->updateService->execute(['key' => $key, 'timeout' => $timeout]);
		} else {
			$this->insertService->execute(['key' => $key, 'timeout' => $timeout, 'try' => '2000-01-01 00:00:00', 'run' => '2000-01-01 00:00:00']);
		}

		return $this;
	}
	
	/**
	 * @param callable $fn
	 * @return int
	 * @throws Throwable
	 */
	public function lockAndIterateServices($fn) {
		$count = 0;
		$this->pdo->exec('START TRANSACTION');
		try {
			$services = $this->fetchServices();
			foreach($services as $service) {
				$this->updateTryDate->execute(['key' => $service->getKey()]);
				$fn($service);
				$this->updateRunDate->execute(['key' => $service->getKey()]);
				$count++;
			}
			$this->pdo->exec('COMMIT');
			return $count;
		} catch(Throwable $e) {
			$this->pdo->exec('ROLLBACK');
			throw $e;
		}
	}
	
	/**
	 * @return Service[]|Generator
	 */
	private function fetchServices() {
		$this->selectServices->execute();
		try {
			$services = $this->selectServices->fetchAll(PDO::FETCH_ASSOC);
			foreach($services as $service) {
				yield new Service($service['service_key']);
			}
		} finally {
			$this->selectServices->closeCursor();
		}
	}
}
