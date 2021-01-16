<?php
namespace Kir\Services\Cmd\Dispatcher\Builder;

use Kir\Services\Cmd\Dispatcher\AttributeRepositories\MySQLAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use PDO;

class MySQLBuilder {
	/** @var PDO */
	private $pdo;
	/** @var bool */
	private $useLocking = true;
	/** @var string */
	private $lockPrefix = '';
	
	/**
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo) {
		$this->pdo = $pdo;
	}
	
	/**
	 * Enabled by default.
	 * You can disable global locking (using MySQL's GET_LOCK) per service-run.
	 * A Lock will be optained before a service runs and will be released, is the service is done
	 * (or an exception was thrown during the run)
	 *
	 * @param bool $useLocking
	 * @return $this
	 */
	public function useLocking(bool $useLocking = true): self {
		$this->useLocking = $useLocking;
		return $this;
	}
	
	/**
	 * Set the lock-prefix for the MySQL-`GET_LOCK` name.
	 *
	 * You can think of it as calling the function like this:
	 * `SELECT GET_LOCK(CONCAT(<lock-prefix>, <registered-service-name>), 0)`
	 *
	 * Something like `my-app:` would result in `my-app:service-xy` if the particular service was registered as
	 * `service-xy`. The Lock is per service. Between two services, the lock will be released and reoptained.
	 *
	 * @param string $lockPrefix
	 * @return $this
	 */
	public function setLockPrefix(string $lockPrefix = ''): self {
		$this->lockPrefix = $lockPrefix;
		return $this;
	}
	
	/**
	 * @return DefaultDispatcher
	 */
	public function build(): DefaultDispatcher {
		$repos = new MySQLAttributeRepository($this->pdo, [
			'use-locking' => $this->useLocking,
			'lock-prefix' => $this->lockPrefix,
		]);
		return new DefaultDispatcher($repos);
	}
}