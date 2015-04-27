<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use Exception;
use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Psr\Log\LoggerInterface;

class DefaultDispatcher implements Dispatcher {
	/** @var AttributeRepository */
	private $attributeRepository = null;
	/** @var callable[] */
	private $services = array();
	/** @var MethodInvoker */
	private $methodInvoker;
	/** @var LoggerInterface */
	private $logger;
	/** @var array */
	private $standardTimeouts = array();
	/** @var callable[] */
	private $listeners = array();

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
	 * @param int $interval
	 * @param $callable
	 * @return $this
	 */
	public function register($key, $interval, $callable) {
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
	 * @throws \Exception
	 * @return int Number of successfully executed services
	 */
	public function run() {
		$services = $this->attributeRepository->fetchServices();
		$count = 0;
		foreach($services as $service) {
			if(array_key_exists($service, $this->standardTimeouts)) {
				$standardTimeout = $this->standardTimeouts[$service];
			} else {
				$standardTimeout = 0;
			}
			$eventParams = array(
				'serviceName' => $service,
				'serviceTimeout' => $standardTimeout
			);
			try {
				$this->fireEvent('service-start', $eventParams);
				$this->attributeRepository->markTry($service);
				if($this->methodInvoker !== null) {
					$this->methodInvoker->invoke($this->services[$service], $eventParams);
				} else {
					call_user_func($this->services[$service], $service);
				}
				$this->attributeRepository->markRun($service);
				$this->fireEvent('service-success', $eventParams);
				$count++;
			} catch (\Exception $e) {
				$eventParams['exception'] = $e;
				$this->fireEvent('service-failure', $eventParams);
				if($this->logger !== null) {
					$this->logger->critical($e->getMessage(), array('exception' => $e));
				} else {
					throw $e;
				}
			}
		}
		return $count;
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
