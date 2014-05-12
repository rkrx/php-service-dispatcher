<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

use DateTimeZone;
use Kir\Services\Cmd\Dispatcher\Attributes;

class Service {
	/**
	 * @var callable
	 */
	private $callback;

	/**
	 * @var int
	 */
	private $interval;

	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var Attributes
	 */
	private $setting;

	/**
	 * @param $key
	 * @param $interval
	 * @param callable $callback
	 * @param Attributes $setting
	 */
	public function __construct($key, $interval, callable $callback, Attributes $setting) {
		$this->callback = $callback;
		$this->interval = $interval;
		$this->key = $key;
		$this->setting = $setting;
	}

	/**
	 * @return bool
	 */
	public function isActive() {
		$lastRunTime = clone $this->getAttributes()->getLastRunTime();
		$lastRunTime->modify("+{$this->interval} seconds");
		$lastRunTime->setTimezone(new DateTimeZone('UTC'));
		return ;
	}

	/**
	 * @return callable
	 */
	public function getCallback() {
		return $this->callback;
	}

	/**
	 * @return int
	 */
	public function getInterval() {
		return $this->interval;
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * @return Attributes
	 */
	public function getAttributes() {
		return $this->setting;
	}
}