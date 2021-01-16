<?php
namespace Kir\Services\Cmd\Dispatcher;

use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Builder\MySQLBuilder;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use PDO;

class ServiceDispatcherBuilder {
	public static function withSQLite(string $filename): DefaultDispatcher {
		$repos = new SqliteAttributeRepository(new PDO(sprintf('sqlite:%s', $filename)));
		return new DefaultDispatcher($repos);
	}
	
	public static function withMySQL(PDO $pdo): MySQLBuilder {
		return new MySQLBuilder($pdo);
	}
}
