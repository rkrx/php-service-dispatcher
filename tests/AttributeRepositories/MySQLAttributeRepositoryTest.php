<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

class MySQLAttributeRepositoryTest extends TestCase {
	const TABLE_NAME = 'services_test';
	
	public function testSetLastTryDate() {
		$pdo = $this->createConnection();
		$this->transaction($pdo, function (PDO $pdo) {
			$repository = $this->getRepos($pdo);
			$repository->register('xx');
			$repository->setLastTryDate('xx', date_create_immutable());
			$row = $repository->getRowByKey('xx');
			self::assertEquals('xx', $row->service_key ?? null);
			self::assertNotNull($row->service_last_try);
			self::assertNull($row->service_last_run);
			self::assertNull($row->service_next_run);
		});
	}
	
	public function testSetLastRunDate() {
		$pdo = $this->createConnection();
		$this->transaction($pdo, function (PDO $pdo) {
			$repository = $this->getRepos($pdo);
			$repository->register('xx');
			$repository->setLastRunDate('xx', date_create_immutable());
			$row = $repository->getRowByKey('xx');
			self::assertEquals('xx', $row->service_key);
			self::assertNull($row->service_last_try);
			self::assertNotNull($row->service_last_run);
			self::assertNull($row->service_next_run);
		});
	}
	
	public function testSetNextRunDate() {
		$pdo = $this->createConnection();
		$this->transaction($pdo, function (PDO $pdo) {
			$repository = $this->getRepos($pdo);
			$repository->register('xx');
			$repository->setNextRunDate('xx', date_create_immutable());
			$row = $repository->getRowByKey('xx');
			self::assertEquals('xx', $row->service_key);
			self::assertNull($row->service_last_try);
			self::assertNull($row->service_last_run);
			self::assertNotNull($row->service_next_run);
		});
	}
	
	public function testOldTableUpgrade() {
		$pdo = $this->createConnection();
		$this->transaction($pdo, function (PDO $pdo) {
			$pdo->exec(sprintf("CREATE TABLE `%s` (`service_key` VARCHAR(255) NOT NULL DEFAULT '', `service_last_try` DATETIME NULL DEFAULT NULL, `service_last_run` DATETIME NULL DEFAULT NULL, `service_timeout` INTEGER NULL DEFAULT NULL, PRIMARY KEY (`service_key`));", self::TABLE_NAME));
			$pdo->exec(sprintf("INSERT INTO %s (service_key, service_last_try, service_last_run, service_timeout) VALUES ('test1', '2020-01-01 00:00:00', '2020-01-02 00:00:00', 3600)", self::TABLE_NAME));
			$repository = $this->getRepos($pdo);
			$row = $repository->getRowByKey('test1');
			self::assertEquals('test1', $row->service_key);
			self::assertNotNull($row->service_last_try);
			self::assertNotNull($row->service_last_run);
			self::assertNotNull($row->service_next_run);
		});
	}
	
	public function testCompleteRun() {
		$pdo = $this->createConnection();
		$this->transaction($pdo, function (PDO $pdo) {
			$repository = $this->getRepos($pdo);
			$dp = new DefaultDispatcher($repository);
			$data = (object) ['run' => false];
			$dp->register('test1', '03:00', function () use ($data) {
				$data->run = true;
			});
			$dp->run();
			self::assertTrue($data->run);
			$data->run = false;
			$dp->run();
			self::assertFalse($data->run);
			$row = $repository->getRowByKey('test1');
			self::assertRegExp('/^\\d{4}\\-\\d{2}\\-\\d{2} 03:00:00$/', $row->service_next_run);
		});
	}
	
	private function getRepos(PDO $pdo) {
		return new MySQLAttributeRepository($pdo, self::TABLE_NAME);
	}
	
	private function transaction(PDO $pdo, $fn) {
		try {
			$pdo->exec(sprintf('DROP TABLE IF EXISTS %s', self::TABLE_NAME));
			$pdo->beginTransaction();
			$fn($pdo);
		} finally {
			$pdo->rollBack();
		}
	}
	
	private function createConnection(): PDO {
		return new PDO('mysql:host=127.0.0.1;port=3306;dbname=test;charset=UTF8', 'root');
	}
}
