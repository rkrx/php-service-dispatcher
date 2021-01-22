<?php
namespace Kir\Services\Cmd\Dispatcher;

use Kir\Services\Cmd\Dispatcher\Builder\MySQLBuilder;
use Kir\Services\Cmd\Dispatcher\Builder\SqliteBuilder;
use PDO;

class ServiceDispatcherBuilder {
	/**
	 * @param string $filename
	 * @return SqliteBuilder
	 */
	public static function withSQLite(string $filename): SqliteBuilder {
		return new SqliteBuilder($filename);
	}
	
	/**
	 * @param PDO $pdo The actual PDO connection
	 * @param string $tableName The mysql-Table to store service dispatching information in
	 * @return MySQLBuilder
	 */
	public static function withMySQL(PDO $pdo, string $tableName): MySQLBuilder {
		return new MySQLBuilder($pdo, $tableName);
	}
}
