<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use Exception;
use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\Common\IntervalParser;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class DefaultDispatcher implements Dispatcher {
	/** @var AttributeRepository */
	private $attributeRepository;
	/** @var callable[] */
	private $services = [];
	/** @var MethodInvoker */
	private $methodInvoker;
	/** @var LoggerInterface */
	private $logger;
	/** @var array */
	private $standardTimeouts = [];
	/** @var array */
	private $listeners = [];

	/**
	 * @param AttributeRepository $settings
	 * @param MethodInvoker $methodInvoker
	 * @param LoggerInterface $logger
	 */
	public function __construct(AttributeRepository $settings, MethodInvoker $methodInvoker = null, LoggerInterface $logger = null) {
		$this->attributeRepository = $settings;
		$this->methodInvoker = $methodInvoker;
		$this->logger = $logger;
	}

	/**
	 * @param string $key
	 * @param string|int $interval
	 * @param $callable
	 * @return $this
	 */
	public function register($key, $interval, $callable) {
		$interval = IntervalParser::parse($interval);
		$this->attributeRepository->store($key, $interval);
		$this->services[$key] = $callable;
		$this->standardTimeouts[$key] = $interval;
		return $this;
	}

	/**
	 * @param string $event
	 * @param callable $fn
	 * @return $this
	 */
	public function on($event, $fn) {
		if(!array_key_exists($event, $this->listeners)) {
			$this->listeners[$event] = array();
		}
		$this->listeners[$event][] = $fn;
		return $this;
	}

	/**
	 * @return int Number of successfully executed services
	 */
	public function run() {
		return $this->attributeRepository->lockAndIterateServices(function (Service $service) {
			if(!array_key_exists($service->getKey(), $this->services)) {
				return;
			}
			if(array_key_exists($service->getKey(), $this->standardTimeouts)) {
				$standardTimeout = $this->standardTimeouts[$service->getKey()];
			} else {
				$standardTimeout = 0;
			}
			$eventParams = [
				'serviceName' => $service,
				'serviceTimeout' => $standardTimeout
			];
			try {
				$this->fireEvent('service-start', $eventParams);
				if($this->methodInvoker !== null) {
					$result = $this->methodInvoker->invoke($this->services[$service->getKey()], $eventParams);
				} else {
					$result = call_user_func($this->services[$service->getKey()], $service);
				}
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
