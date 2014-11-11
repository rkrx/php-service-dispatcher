<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use PDO;
use Exception;
use PDOStatement;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;

class SqliteAttributeRepository implements AttributeRepository {
	/**
	 * @var PDO
	 */
	private $pdo = null;

	/**
	 * @var PDOStatement
	 */
	private $selectServices = null;

	/**
	 * @var PDOStatement
	 */
	private $hasService = null;

	/**
	 * @var PDOStatement
	 */
	private $insertService = null;

	/**
	 * @var PDOStatement
	 */
	private $updateService = null;

	/**
	 * @var PDOStatement
	 */
	private $updateRunDate = null;

	/**
	 * @var array
	 */
	private $services = array();

	/**
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo) {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec("CREATE TABLE IF NOT EXISTS services (service_key STRING PRIMARY KEY, service_last_try DATETIME, service_last_run DATETIME, service_timeout INTEGER);");

		$this->selectServices = $pdo->prepare('SELECT service_key FROM services WHERE datetime(datetime(\'now\'), \'-\'||service_timeout||\' seconds\') > service_last_run ORDER BY MAX(service_last_try, service_last_run) ASC;');
		$this->hasService = $pdo->prepare('SELECT COUNT(*) FROM services WHERE service_key=:key;');
		$this->insertService = $pdo->prepare('INSERT INTO services (service_key, service_last_try, service_last_run, service_timeout) VALUES (:key, :try, :run, :timeout);');
		$this->updateService = $pdo->prepare('UPDATE services SET service_timeout=:timeout WHERE service_key=:key;');
		$this->updateTryDate = $pdo->prepare('UPDATE services SET service_last_try=datetime(\'now\') WHERE service_key=:key;');
		$this->updateRunDate = $pdo->prepare('UPDATE services SET service_last_run=datetime(\'now\') WHERE service_key=:key;');
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