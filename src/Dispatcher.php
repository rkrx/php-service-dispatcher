<?php
namespace Kir\Services\Cmd\Dispatcher;

use Exception;

interface Dispatcher {
	const ONE_DAY = 86400;
	const ONE_HOUR = 3600;
	const ONE_MINUTE = 60;

	/**
	 * @param string $key
	 * @param int $interval
	 * @param $callable
	 * @return $this
	 */
	public function register($key, $interval, $callable);

	/**
	 * @throws Exception
	 * @return $this
	 */
	public function run();
} 