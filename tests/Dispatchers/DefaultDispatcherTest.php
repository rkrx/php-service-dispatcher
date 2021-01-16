<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class DefaultDispatcherTest extends TestCase {
	public function test1() {
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
		
		$nextRunValues = $pdo->query('SELECT service_key, service_next_run FROM services')->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? null);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? null);
	}

	public function test2() {
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

		$nextRunValues = $pdo->query('SELECT service_key, service_next_run FROM services')->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? null);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? null);
	}

	public function test3() {
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
		
		$nextRunValues = $pdo->query('SELECT service_key, service_next_run FROM services')->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? null);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? null);
	}

	public function test4() {
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
		
		$nextRunValues = $pdo->query('SELECT service_key, service_next_run FROM services')->fetchAll(PDO::FETCH_KEY_PAIR);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T03:00:00$/', $nextRunValues['service1'] ?? null);
		self::assertRegExp('/^\\d{4}-\\d{2}-\\d{2}T06:00:00$/', $nextRunValues['service2'] ?? null);
	}
}
