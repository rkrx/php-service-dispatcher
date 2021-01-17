<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use DateTimeInterface;
use Exception;
use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Common\IntervalParser;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class DefaultDispatcher implements Dispatcher {
	/** @var AttributeRepository */
	private $attributeRepository;
	/** @var object[] */
	private $services = [];
	/** @var MethodInvoker */
	private $methodInvoker;
	/** @var LoggerInterface */
	private $logger;
	/** @var array */
	private $listeners = [];
	
	/**
	 * @param AttributeRepository $attributeRepository
	 * @param MethodInvoker|null $methodInvoker
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(AttributeRepository $attributeRepository, MethodInvoker $methodInvoker = null, LoggerInterface $logger = null) {
		$this->attributeRepository = $attributeRepository;
		$this->methodInvoker = $methodInvoker;
		$this->logger = $logger;
	}

	/**
	 * @param string $key
	 * @param string|int $interval
	 * @param callable $callable
	 * @return $this
	 */
	public function register(string $key, $interval, callable $callable) {
		$this->attributeRepository->register($key);
		$this->services[$key] = (object) [
			'fn' => $callable,
			'key' => $key,
			'interval' => $interval
		];
		return $this;
	}

	/**
	 * @param string $event
	 * @param callable $fn
	 * @return $this
	 */
	public function on(string $event, callable $fn) {
		if(!array_key_exists($event, $this->listeners)) {
			$this->listeners[$event] = [];
		}
		$this->listeners[$event][] = $fn;
		return $this;
	}

	/**
	 * @param DateTimeInterface|null $now
	 * @return int Number of successfully executed services
	 */
	public function run(DateTimeInterface $now = null) {
		$now = $now ?? date_create_immutable();
		return $this->attributeRepository->lockAndIterateServices($now, function (Service $service) {
			if(!array_key_exists($service->getKey(), $this->services)) {
				return;
			}
			$eventParams = ['serviceName' => $service->getKey()];
			try {
				$this->fireEvent('service-start', $eventParams);
				$serviceData = $this->services[$service->getKey()];
				$this->attributeRepository->setLastTryDate($service->getKey(), date_create_immutable());
				if($this->methodInvoker !== null) {
					$result = $this->methodInvoker->invoke($serviceData->fn, $eventParams);
				} else {
					$result = call_user_func($serviceData->fn, $service);
				}
				$this->attributeRepository->setLastRunDate($service->getKey(), date_create_immutable());
				$nextRunDate = IntervalParser::getNext($serviceData->interval);
				$this->attributeRepository->setNextRunDate($serviceData->key, $nextRunDate);
				if($result !== false) {
					$this->fireEvent('service-success', $eventParams);
				}
			} catch (Throwable $e) {
				$eventParams['exception'] = $e;
				$this->fireEvent('service-failure', $eventParams);
				if($this->logger !== null) {
					$this->logger->critical("{$service}: {$e->getMessage()}", ['exception' => $e]);
				} else {
					throw new RuntimeException("{$service}: {$e->getMessage()}", (int) $e->getCode(), $e);
				}
			}
		});
	}

	/**
	 * @param string $event
	 * @param array $params
	 */
	private function fireEvent($event, $params) {
		if(array_key_exists($event, $this->listeners)) {
			try {
				foreach($this->listeners[$event] as $listener) {
					$this->methodInvoker->invoke($listener, $params);
				}
			} catch (Exception $e) {
				// Supress exceptions emitted by events
				if($this->logger !== null) {
					$this->logger->critical($e->getMessage(), array('exception' => $e));
				}
			}
		}
	}
}
