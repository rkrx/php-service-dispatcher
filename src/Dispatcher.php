<?php
namespace Kir\Services\Cmd\Dispatcher;

use DateTimeInterface;
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
	public function register(string $key, $interval, callable $callable);

	/**
	 * @param string $event
	 * @param callable $fn
	 * @return $this
	 */
	public function on(string $event, callable $fn);
	
	/**
	 * @param DateTimeInterface $now
	 * @return int Count of services successfully started
	 * @throws Exception
	 */
	public function run(DateTimeInterface $now);
}
