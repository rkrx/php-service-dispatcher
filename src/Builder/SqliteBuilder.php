<?php
namespace Kir\Services\Cmd\Dispatcher\Builder;

use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;
use PDO;

class SqliteBuilder {
	private MethodInvoker|null $methodInvoker = null;
	
	/**
	 * @param string $filename
	 */
	public function __construct(
		private readonly string $filename
	) {}
	
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
	 * @return DefaultDispatcher
	 */
	public function build(): DefaultDispatcher {
		$pdo = new PDO(sprintf('sqlite:%s', $this->filename));
		$repos = new SqliteAttributeRepository($pdo);
		return new DefaultDispatcher($repos, $this->methodInvoker);
	}
}