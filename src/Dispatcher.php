<?php
namespace Kir\Services\Cmd\Dispatcher;

use Exception;

interface Dispatcher {
	const ONE_DAY = 86400;
	const ONE_HOUR = 3600;
	const ONE_MINUTE = 60;

	/**
	 * @param string $key
	 * @param string|int $interval
	 * @param $callable
	 * @return $this
	 */
	public function register($key, $interval, $callable);

	/**
	 * @param string $event
	 * @param callable $fn
	 * @return $this
	 */
	public function on($event, $fn);

	/**
	 * @throws Exception
	 * @return int Count of services successfully started
	 */
	public function run();
}
