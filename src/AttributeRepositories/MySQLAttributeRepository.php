<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use PDO;
use Exception;
use PDOStatement;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;

class MySQLAttributeRepository implements AttributeRepository {
	/** @var PDO */
	private $pdo = null;
	/** @var PDOStatement */
	private $selectServices = null;
	/** @var PDOStatement */
	private $hasService = null;
	/** @var PDOStatement */
	private $insertService = null;
	/** @var PDOStatement */
	private $updateService = null;
	/** @var PDOStatement */
	private $updateTryDate = null;
	/** @var PDOStatement */
	private $updateRunDate = null;
	/** @var array */
	private $services = array();

	/**
	 * @param PDO $pdo
	 * @param string $tableName
	 */
	public function __construct(PDO $pdo, $tableName = 'services') {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec("CREATE TABLE IF NOT EXISTS `{$tableName}` (`service_key` VARCHAR(255) NOT NULL DEFAULT '', `service_last_try` DATETIME NULL DEFAULT '2000-01-01 00:00:00', `service_last_run` DATETIME NULL DEFAULT '2000-01-01 00:00:00', `service_timeout` INT UNSIGNED NULL DEFAULT '0', PRIMARY KEY (`service_key`));");

		$this->selectServices = $pdo->prepare("SELECT service_key FROM `{$tableName}` WHERE DATE_ADD(service_last_run, INTERVAL service_timeout SECOND) ORDER BY GREATEST(service_last_try, service_last_run) ASC;");
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
		$this->hasService->bindValue('key', $key);
		$this->hasService->execute();
		$count = $this->hasService->fetchColumn(0);
		return $count > 0;
	}

	/**
	 * @param string $key
	 * @param int $timeout
	 * @param array $data
	 * @throws Exception
	 * @return $this
	 */
	public function store($key, $timeout, array $data = array()) {
		$key = trim(strtolower($key));
		if(!in_array($key, $this->services)) {
			$this->services[] = $key;
		} else {
			throw new Exception("Duplicate service: {$key}");
		}

		if($this->has($key)) {
			$this->updateService->bindValue('key', $key);
			$this->updateService->bindValue('timeout', $timeout);
			$this->updateService->execute();
		} else {
			$this->insertService->bindValue('key', $key);
			$this->insertService->bindValue('timeout', $timeout);
			$this->insertService->bindValue('try', '2000-01-01 00:00:00');
			$this->insertService->bindValue('run', '2000-01-01 00:00:00');
			$this->insertService->execute();
		}

		return $this;
	}

	/**
	 * @param string $key
	 * @return $this
	 */
	public function markTry($key) {
		$this->updateTryDate->bindValue('key', $key);
		$this->updateTryDate->execute();
		return $this;
	}

	/**
	 * @param string $key
	 * @return $this
	 */
	public function markRun($key) {
		$this->updateRunDate->bindValue('key', $key);
		$this->updateRunDate->execute();
		return $this;
	}

	/**
	 * @return Service[]
	 */
	public function fetchServices() {
		$this->selectServices->execute();
		$services = $this->selectServices->fetchAll(PDO::FETCH_ASSOC);
		$result = array();
		foreach($services as $service) {
			$result[] = $service['service_key'];
		}
		$this->selectServices->closeCursor();
		return $result;
	}
}
