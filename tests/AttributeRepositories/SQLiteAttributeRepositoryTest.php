<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

class SQLiteAttributeRepositoryTest extends TestCase {
	public function testSetLastTryDate() {
		$repository = $this->getRepos();
		$repository->register('xx');
		$repository->setLastTryDate('xx', date_create_immutable());
		$row = $repository->getRowByKey('xx');
		self::assertEquals('xx', $row->service_key ?? null);
		self::assertNotNull('service_last_try', $row->service_last_try ?? null);
	}
	
	public function testSetLastRunDate() {
		$repository = $this->getRepos();
		$repository->register('xx');
		$repository->setLastRunDate('xx', date_create_immutable());
		$row = $repository->getRowByKey('xx');
		self::assertEquals('xx', $row->service_key ?? null);
		self::assertNotNull('service_last_try', $row->service_last_run ?? null);
	}
	
	public function testSetNextRunDate() {
		$repository = $this->getRepos();
		$repository->register('xx');
		$repository->setNextRunDate('xx', date_create_immutable());
		$row = $repository->getRowByKey('xx');
		self::assertEquals('xx', $row->service_key ?? null);
		self::assertNotNull('service_last_try', $row->service_next_try ?? null);
	}
	
	public function testCompleteRun() {
		$repository = $this->getRepos();
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
		self::assertRegExp('/^\\d{4}\\-\\d{2}\\-\\d{2}T03:00:00$/', $row->service_next_run);
	}
	
	public function getRepos(): SqliteAttributeRepository {
		return new SqliteAttributeRepository(new PDO('sqlite::memory:'));
	}
}
