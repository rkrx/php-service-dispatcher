<?php
namespace Kir\Services\Cmd\Dispatcher;

use Exception;

interface Dispatcher {
	/**
	 * @param string $key
	 * @param int $interval
	 * @param callable $callable
	 * @return $this
	 */
	public function register($key, $interval, callable $callable);

	/**
	 * @throws Exception
	 * @return $this
	 */
	public function run();
} 