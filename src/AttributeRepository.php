<?php
namespace Kir\Services\Cmd\Dispatcher;

use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher\Service;

interface AttributeRepository {
	/**
	 * @param string $key
	 * @param int $timeout
	 * @return $this
	 */
	public function store($key, $timeout);

	/**
	 * @param string $key
	 * @return $this
	 */
	public function markTry($key);

	/**
	 * @param string $key
	 * @return $this
	 */
	public function markRun($key);

	/**
	 * @return string[]
	 */
	public function fetchServices();
}