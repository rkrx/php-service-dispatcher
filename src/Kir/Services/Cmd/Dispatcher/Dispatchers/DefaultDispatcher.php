<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use DateTime;
use Exception;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\ServiceSorter;

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
	 * @var ServiceSorter
	 */
	private $sorter = null;

	/**
	 * @param AttributeRepository $settings
	 */
	public function __construct(AttributeRepository $settings) {
		$this->attributeRepository = $settings;
		$this->sorter = new ServiceSorter();
	}

	/**
	 * @param string $key
	 * @param int $interval
	 * @param callable $callable
	 * @return $this
	 */
	public function register($key, $interval, callable $callable) {
		$this->attributeRepository->store($key, $interval);
		$this->services[$key] = $callable;
		return $this;
	}

	/**
	 * @throws Exception
	 * @return $this
	 */
	public function run() {
		$services = $this->attributeRepository->fetchServices();
		foreach($services as $service) {
			$this->attributeRepository->markTry($service);
			call_user_func($this->services[$service]);
			$this->attributeRepository->markRun($service);
		}
		return $this;
	}
}