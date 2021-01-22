<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use DateTimeInterface;
use Generator;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use PDO;
use PDOStatement;
use RuntimeException;

class SqliteAttributeRepository implements AttributeRepository {
	private const SQLITE_DATETIME_FORMAT = 'Y-m-d\\TH:i:s';
	
	/** @var PDO */
	private $pdo;
	/** @var PDOStatement */
	private $registerRow;
	/** @var PDOStatement */
	private $selectServices;
	/** @var PDOStatement */
	private $hasService;
	/** @var PDOStatement */
	private $getData;
	/** @var PDOStatement */
	private $setNextRun;
	/** @var PDOStatement */
	private $setTryDate;
	/** @var PDOStatement */
	private $setLastRun;

	/**
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo) {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo = $pdo;
		
		// https://stackoverflow.com/a/8442173
		$this->migrate(1, 'CREATE TABLE IF NOT EXISTS "services" ("service_key" STRING PRIMARY KEY, "service_last_try" DATETIME, "service_last_run" DATETIME, "service_timeout" INTEGER);');
		$this->migrate(2, 'CREATE TABLE IF NOT EXISTS "services_new" ("service_key" STRING PRIMARY KEY, "service_last_try" DATETIME, "service_last_run" DATETIME, "service_next_run" INTEGER);');
		$this->migrate(3, 'INSERT INTO "services_new" ("service_key", "service_last_try", "service_last_run", "service_next_run") SELECT "service_key", "service_last_try", "service_last_run", DATETIME("service_last_run",  \'+\' || "service_timeout" || \' seconds\') FROM "services"');
		$this->migrate(4, 'DROP TABLE "services"');
		$this->migrate(5, 'ALTER TABLE "services_new" RENAME TO "services"');
		
		$this->selectServices = $pdo->prepare('SELECT "service_key", "service_last_try", "service_last_run" FROM "services" WHERE "service_next_run" IS NULL OR DATETIME("service_next_run") <= DATETIME(:dt) ORDER BY MAX("service_last_try", "service_last_run");');
		$this->registerRow = $pdo->prepare('INSERT OR IGNORE INTO "services" ("service_key") VALUES (:key);');
		$this->hasService = $pdo->prepare('SELECT COUNT(*) FROM "services" WHERE "service_key"=:key;');
		$this->getData = $pdo->prepare('SELECT "service_key", "service_last_try", "service_last_run", "service_next_run" FROM "services" WHERE "service_key"=:key;');
		$this->setTryDate = $pdo->prepare('UPDATE "services" SET "service_last_try"=:dt WHERE "service_key"=:key');
		$this->setLastRun = $pdo->prepare('UPDATE "services" SET "service_last_run"=:dt WHERE "service_key"=:key');
		$this->setNextRun = $pdo->prepare('UPDATE "services" SET "service_next_run"=:dt WHERE "service_key"=:key');
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function has(string $key) {
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
	 * @return SqliteAttributeRepository|void
	 */
	public function register(string $key) {
		$this->registerRow->execute(['key' => $key]);
	}

	/**
	 * @param string $key
	 * @return object
	 */
	public function getRowByKey(string $key) {
		try {
			$this->getData->execute(['key' => $key]);
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
	 * @param DateTimeInterface $now
	 * @param callable $fn
	 * @return int
	 */
	public function lockAndIterateServices(DateTimeInterface $now, callable $fn): int {
		$count = 0;
		$services = $this->fetchServices($now);
		foreach($services as $service) {
			$fn($service);
		}
		return $count;
	}
	
	/**
	 * @param DateTimeInterface $now
	 * @return Service[]|Generator
	 */
	public function fetchServices(DateTimeInterface $now): Generator {
		$this->selectServices->execute(['dt' => $now->format(self::SQLITE_DATETIME_FORMAT)]);
		try {
			$services = $this->selectServices->fetchAll(PDO::FETCH_ASSOC);
			foreach($services as $service) {
				yield new Service($service['service_key']);
			}
		} finally {
			$this->selectServices->closeCursor();
		}
	}
	
	/**
	 * @param int $version
	 * @param string $statement
	 */
	private function migrate(int $version, string $statement): void {
		$currentVersion = (int) $this->pdo->query('PRAGMA user_version')->fetchColumn(0);
		if($currentVersion < $version) {
			$this->pdo->exec($statement);
			$this->pdo->exec("PRAGMA user_version={$version}");
		}
	}
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return void
	 */
	public function setLastTryDate(string $key, DateTimeInterface $datetime): void {
		$this->setTryDate->execute(['key' => $key, 'dt' => $datetime->format(self::SQLITE_DATETIME_FORMAT)]);
	}
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return void
	 */
	public function setLastRunDate(string $key, DateTimeInterface $datetime): void {
		$this->setLastRun->execute(['key' => $key, 'dt' => $datetime->format(self::SQLITE_DATETIME_FORMAT)]);
	}
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return void
	 */
	public function setNextRunDate(string $key, DateTimeInterface $datetime): void {
		$this->setNextRun->execute(['key' => $key, 'dt' => $datetime->format(self::SQLITE_DATETIME_FORMAT)]);
	}
}