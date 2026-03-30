<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use Kir\Services\Cmd\Dispatcher\ServiceDispatcherBuilder;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class DefaultDispatcherTest extends TestCase {
	public function test1(): void {
		$pdo = new PDO('sqlite::memory:');
		$data = (object) ['list' => []];

		$repos = new SqliteAttributeRepository($pdo);
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service1", "2000-01-01", "2000-01-01", "2000-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service2", "2001-01-01", "2000-01-01", "2000-01-01")');
		
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', '03:00', static function () use ($data) {
			$data->list[] = 'a';
		})->register('service2', '06:00', static function () use ($data) {
			$data->list[] = 'b';
		})->run();

		self::assertEquals(['a', 'b'], $data->list);

		/** @var PDOStatement $statement */
		$statement = $pdo->query('SELECT service_key, service_next_run FROM services');
		/** @var array{service1?: string, service2?: string} $nextRunValues */
		$nextRunValues = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? '');
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? '');
	}

	public function test2(): void {
		$pdo = new PDO('sqlite::memory:');
		$data = (object) ['list' => []];

		$repos = new SqliteAttributeRepository($pdo);
		
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service1", "2001-01-01", "2000-01-01", "2000-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service2", "2000-01-01", "2000-01-01", "2000-01-01")');
		
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', '03:00', static function () use ($data) {
			$data->list[] = 'a';
		})->register('service2', '06:00', static function () use ($data) {
			$data->list[] = 'b';
		})->run();

		self::assertEquals(['b', 'a'], $data->list);

		/** @var PDOStatement $statement */
		$statement = $pdo->query('SELECT service_key, service_next_run FROM services');
		/** @var array{service1?: string, service2?: string} $nextRunValues */
		$nextRunValues = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? '');
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? '');
	}

	public function test3(): void {
		$pdo = new PDO('sqlite::memory:');
		$data = (object) ['list' => []];

		$repos = new SqliteAttributeRepository($pdo);
		
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service1", "2000-01-01", "2000-01-01", "2000-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service2", "2000-01-01", "2001-01-01", "2001-01-01")');
		
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', '03:00', static function () use ($data) {
			$data->list[] = 'a';
		})->register('service2', '06:00', static function () use ($data) {
			$data->list[] = 'b';
		})->run();

		self::assertEquals(['a', 'b'], $data->list);

		/** @var PDOStatement $statement */
		$statement = $pdo->query('SELECT service_key, service_next_run FROM services');
		/** @var array{service1?: string, service2?: string} $nextRunValues */
		$nextRunValues = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? '');
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? '');
	}

	public function test4(): void {
		$pdo = new PDO('sqlite::memory:');
		$data = (object) ['list' => []];

		$repos = new SqliteAttributeRepository($pdo);
		
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service1", "2000-01-01", "2001-01-01", "2001-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run, service_next_run) VALUES ("service2", "2000-01-01", "2000-01-01", "2000-01-01")');
		
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', '03:00', static function () use ($data) {
			$data->list[] = 'a';
		})->register('service2', '06:00', static function () use ($data) {
			$data->list[] = 'b';
		})->run();

		self::assertEquals(['b', 'a'], $data->list);

		/** @var PDOStatement $statement */
		$statement = $pdo->query('SELECT service_key, service_next_run FROM services');
		/** @var array{service1?: string, service2?: string} $nextRunValues */
		$nextRunValues = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? '');
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? '');
	}

	public function testEventHandling(): void {
		$data = (object) ['serviceStart' => null, 'serviceSuccess' => null];

		$dispatcher = ServiceDispatcherBuilder::withSQLite(':memory:')->build();
		$dispatcher->register('test', '* * * * *', function () {});
		$dispatcher->on('service-start', static function (string $serviceName) use ($data) {
			$data->serviceStart = $serviceName;
		});
		
		$dispatcher->on('service-success', static function (string $serviceName) use ($data) {
			$data->serviceSuccess = $serviceName;
		});
		$dispatcher->run();
		
		self::assertEquals((object) ['serviceStart' => 'test', 'serviceSuccess' => 'test'], $data);
	}

	public function testServiceReceivesConfiguredInterval(): void {
		$pdo = new PDO('sqlite::memory:');
		$data = [];

		$dispatcher = new DefaultDispatcher(new SqliteAttributeRepository($pdo));
		$dispatcher
			->register('service1', 60, static function (array $service) use (&$data) {
				$data[$service['serviceName']] = $service['interval'];
			})
			->register('service2', '06:00', static function (array $service) use (&$data) {
				$data[$service['serviceName']] = $service['interval'];
			})
			->run();

		self::assertSame([
			'service1' => 60,
			'service2' => '06:00'
		], $data);
	}
}
