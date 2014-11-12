<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use DateTime;
use Exception;
use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\ServiceSorter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DefaultDispatcher implements Dispatcher {
	/**
	 * @var AttributeRepository
	 */
	private $attributeRepository = null;
	/**
	 * @var callable[]
	 */
	private $services = array();
	/**
	 * @var MethodInvoker
	 */
	private $methodInvoker;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param AttributeRepository $settings
	 * @param MethodInvoker $methodInvoker
	 * @param LoggerInterface $logger
	 */
	public function __construct(AttributeRepository $settings, MethodInvoker $methodInvoker = null, LoggerInterface $logger = null) {
		$this->attributeRepository = $settings;
		$this->methodInvoker = $methodInvoker;
		$this->logger = $logger !== null ? $logger : new NullLogger();
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
		return $this;
	}

	/**
	 * @throws Exception
	 * @return int Number of sucessfully executed services
	 */
	public function run() {
		$services = $this->attributeRepository->fetchServices();
		$count = 0;
		foreach($services as $service) {
			try {
				$this->attributeRepository->markTry($service);
				if($this->methodInvoker !== null) {
					$this->methodInvoker->invoke($this->services[$service], array('serviceName' => $service));
				} else {
					call_user_func($this->services[$service], $service);
				}
				$this->attributeRepository->markRun($service);
				$count++;
			} catch (\Exception $e) {
				$this->logger->critical($e->getMessage(), array('exception' => $e));
			}
		}
		return $count;
	}
}