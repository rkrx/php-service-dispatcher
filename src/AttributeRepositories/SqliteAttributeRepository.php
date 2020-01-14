<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use Generator;
use PDO;
use PDOStatement;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use RuntimeException;
use Throwable;

class SqliteAttributeRepository implements AttributeRepository {
	/** @var PDO */
	private $pdo;
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

	/**
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo) {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec('CREATE TABLE IF NOT EXISTS services (service_key STRING PRIMARY KEY, service_last_try DATETIME, service_last_run DATETIME, service_timeout INTEGER);');

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
		try {
			$this->hasService->execute(['key' => $key]);
			$count = $this->hasService->fetchColumn(0);
			return $count > 0;
		} finally {
			$this->hasService->closeCursor();
		}
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
		$this->pdo->exec('BEGIN EXCLUSIVE TRANSACTION');
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
	public function fetchServices() {
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