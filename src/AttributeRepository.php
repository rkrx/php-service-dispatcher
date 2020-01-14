<?php
namespace Kir\Services\Cmd\Dispatcher;

interface AttributeRepository {
	/**
	 * @param string $key
	 * @param int $timeout
	 * @return $this
	 */
	public function store($key, $timeout);
	
	/**
	 * @param callable $fn
	 * @return int Successfully executed services
	 */
	public function lockAndIterateServices($fn);
}