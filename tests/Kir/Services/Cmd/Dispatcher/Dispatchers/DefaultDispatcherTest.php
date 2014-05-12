<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use DateTime;
use Exception;
use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use Kir\Services\Cmd\Dispatcher\AttributeRepositories\XmlAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Common\CommonAttributes;
use PDO;
use PHPUnit_Framework_TestCase;
use SplDoublyLinkedList;
use SplString;

class DefaultDispatcherTest extends PHPUnit_Framework_TestCase {
	public function test1() {
		$pdo = new PDO("sqlite::memory:");
		$pdo->exec('CREATE TABLE IF NOT EXISTS services (service_key STRING PRIMARY KEY, service_last_try DATETIME, service_last_run DATETIME, service_timeout INTEGER);');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service1", "2000-01-01", "2000-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service2", "2001-01-01", "2000-01-01")');

		$list = new SplDoublyLinkedList();

		$repos = new SqliteAttributeRepository($pdo);
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', 3600, function () use ($list) {
			$list->push("a");
		})->register('service2', 3600, function () use ($list) {
			$list->push("b");
		})->run();

		$this->assertEquals('i:0;:s:1:"a";:s:1:"b";', $list->serialize());
	}

	public function test2() {
		$pdo = new PDO("sqlite::memory:");
		$pdo->exec('CREATE TABLE IF NOT EXISTS services (service_key STRING PRIMARY KEY, service_last_try DATETIME, service_last_run DATETIME, service_timeout INTEGER);');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service1", "2001-01-01", "2000-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service2", "2000-01-01", "2000-01-01")');

		$list = new SplDoublyLinkedList();

		$repos = new SqliteAttributeRepository($pdo);
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', 3600, function () use ($list) {
			$list->push("a");
		})->register('service2', 3600, function () use ($list) {
			$list->push("b");
		})->run();

		$this->assertEquals('i:0;:s:1:"b";:s:1:"a";', $list->serialize());
	}

	public function test3() {
		$pdo = new PDO("sqlite::memory:");
		$pdo->exec('CREATE TABLE IF NOT EXISTS services (service_key STRING PRIMARY KEY, service_last_try DATETIME, service_last_run DATETIME, service_timeout INTEGER);');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service1", "2000-01-01", "2000-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service2", "2000-01-01", "2001-01-01")');

		$list = new SplDoublyLinkedList();

		$repos = new SqliteAttributeRepository($pdo);
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', 3600, function () use ($list) {
			$list->push("a");
		})->register('service2', 3600, function () use ($list) {
			$list->push("b");
		})->run();

		$this->assertEquals('i:0;:s:1:"a";:s:1:"b";', $list->serialize());
	}

	public function test4() {
		$pdo = new PDO("sqlite::memory:");
		$pdo->exec('CREATE TABLE IF NOT EXISTS services (service_key STRING PRIMARY KEY, service_last_try DATETIME, service_last_run DATETIME, service_timeout INTEGER);');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service1", "2000-01-01", "2001-01-01")');
		$pdo->exec('INSERT INTO services (service_key, service_last_try, service_last_run) VALUES ("service2", "2000-01-01", "2000-01-01")');

		$list = new SplDoublyLinkedList();

		$repos = new SqliteAttributeRepository($pdo);
		$dispatcher = new DefaultDispatcher($repos);
		$dispatcher->register('service1', 3600, function () use ($list) {
			$list->push("a");
		})->register('service2', 3600, function () use ($list) {
			$list->push("b");
		})->run();

		$this->assertEquals('i:0;:s:1:"b";:s:1:"a";', $list->serialize());
	}
}
 