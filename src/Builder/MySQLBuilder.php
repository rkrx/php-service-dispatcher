<?php
namespace Kir\Services\Cmd\Dispatcher\Builder;

use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\AttributeRepositories\MySQLAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use PDO;

class MySQLBuilder {
	/** @var PDO */
	private $pdo;
	/** @var MethodInvoker|null */
	private $methodInvoker;
	/** @var bool */
	private $useLocking = true;
	/** @var string */
	private $lockPrefix = '';
	/** @var string */
	private $tableName;
	
	/**
	 * @param PDO $pdo
	 * @param string $tableName
	 */
	public function __construct(PDO $pdo, string $tableName) {
		$this->pdo = $pdo;
		$this->tableName = $tableName;
	}

	/**
	 * This can be used to enable automatic provisioning of DI objects when service methods are called. (Autowiring)
	 *
	 * @param MethodInvoker|null $methodInvoker
	 * @return $this
	 */
	public function setMethodInvoker(?MethodInvoker $methodInvoker = null): self {
		$this->methodInvoker = $methodInvoker;
		return $this;
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
		$repos = new MySQLAttributeRepository($this->pdo, $this->tableName, [
			'use-locking' => $this->useLocking,
			'lock-prefix' => $this->lockPrefix,
		]);
		return new DefaultDispatcher($repos, $this->methodInvoker);
	}
}