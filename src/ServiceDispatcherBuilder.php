<?php
namespace Kir\Services\Cmd\Dispatcher;

use Kir\Services\Cmd\Dispatcher\Builder\MySQLBuilder;
use Kir\Services\Cmd\Dispatcher\Builder\SqliteBuilder;
use PDO;

class ServiceDispatcherBuilder {
	public static function withSQLite(string $filename): SqliteBuilder {
		return new SqliteBuilder($filename);
	}
	
	public static function withMySQL(PDO $pdo): MySQLBuilder {
		return new MySQLBuilder($pdo);
	}
}
