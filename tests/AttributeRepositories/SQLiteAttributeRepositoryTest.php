<?php
namespace Kir\Services\Cmd\Dispatcher\AttributeRepositories;

use DateTimeImmutable;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use Kir\Services\Cmd\Dispatcher\ServiceDispatcherBuilder;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class SQLiteAttributeRepositoryTest extends TestCase {
	public function testSetLastTryDate(): void {
		$repository = $this->getRepos();
		$repository->register('xx');
		$repository->setLastTryDate('xx', new DateTimeImmutable());
		$row = $repository->getRowByKey('xx');
		self::assertEquals('xx', $row->service_key ?? null);
		self::assertNotNull($row->service_last_try);
		self::assertNull($row->service_last_run);
		self::assertNull($row->service_next_run);
	}
	
	public function testSetLastRunDate(): void {
		$repository = $this->getRepos();
		$repository->register('xx');
		$repository->setLastRunDate('xx', new DateTimeImmutable());
		$row = $repository->getRowByKey('xx');
		self::assertEquals('xx', $row->service_key ?? null);
		self::assertNull($row->service_last_try);
		self::assertNotNull($row->service_last_run);
		self::assertNull($row->service_next_run);
	}
	
	public function testSetNextRunDate(): void {
		$repository = $this->getRepos();
		$repository->register('xx');
		$repository->setNextRunDate('xx', new DateTimeImmutable());
		$row = $repository->getRowByKey('xx');
		self::assertEquals('xx', $row->service_key ?? null);
		self::assertNull($row->service_last_try);
		self::assertNull($row->service_last_run);
		self::assertNotNull($row->service_next_run);
	}
	
	public function testCompleteRun(): void {
		$repository = $this->getRepos();
		$dp = new DefaultDispatcher($repository);
		$data = (object) ['run' => false];
		$dp->register('test1', '03:00', function () use ($data) {
			$data->run = true;
		});
		$dp->run();
		self::assertTrue($data->run); // @phpstan-ignore-line
		$data->run = false;
		$dp->run();
		self::assertFalse($data->run);
		$row = $repository->getRowByKey('test1');
		self::assertRegExp('/^\\d{4}\\-\\d{2}\\-\\d{2}T03:00:00$/', $row->service_next_run ?? '');
	}
	
	public function testAutomaticDatabaseUpgrade(): void {
		copy(__DIR__.'/SQLite/services.db', __DIR__.'/SQLite/services-test.db');
		ServiceDispatcherBuilder::withSQLite(__DIR__.'/SQLite/services-test.db')->build();
		$pdo = new PDO(sprintf('sqlite:%s', __DIR__.'/SQLite/services-test.db'));
		/** @var PDOStatement $statement */
		$statement = $pdo->query('SELECT * FROM "services"');
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		
		$expectedData = [[
			'service_key' => 'test1',
			'service_last_try' => '2020-01-01 00:00:00',
			'service_last_run' => '2020-01-01 00:00:00',
			'service_next_run' => '2020-01-01 00:01:00',
		], [
			'service_key' => 'test2',
			'service_last_try' => '2020-01-01 00:00:00',
			'service_last_run' => '2020-01-01 00:00:00',
			'service_next_run' => '2020-01-01 01:00:00',
		], [
			'service_key' => 'test3',
			'service_last_try' => '2020-01-01 00:00:00',
			'service_last_run' => '2020-01-01 00:00:00',
			'service_next_run' => '2020-01-02 00:00:00',
		]];
		
		self::assertEquals($expectedData, $rows);
	}
	
	public function getRepos(): SqliteAttributeRepository {
		return new SqliteAttributeRepository(new PDO('sqlite::memory:'));
	}
}
