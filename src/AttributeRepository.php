<?php
namespace Kir\Services\Cmd\Dispatcher;

use DateTimeInterface;

interface AttributeRepository {
	/**
	 * @param string $key
	 * @return $this
	 */
	public function register(string $key);
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return $this
	 */
	public function setLastTryDate(string $key, DateTimeInterface $datetime);
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return $this
	 */
	public function setLastRunDate(string $key, DateTimeInterface $datetime);
	
	/**
	 * @param string $key
	 * @param DateTimeInterface $datetime
	 * @return $this
	 */
	public function setNextRunDate(string $key, DateTimeInterface $datetime);
	
	/**
	 * @param DateTimeInterface $now
	 * @param callable $fn
	 * @return int Successfully executed services
	 */
	public function lockAndIterateServices(DateTimeInterface $now, callable $fn): int;
}