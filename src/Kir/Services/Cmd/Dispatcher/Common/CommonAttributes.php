<?php
namespace Kir\Services\Cmd\Dispatcher\Common;

use DateTime;
use Kir\Services\Cmd\Dispatcher\Attributes;

class CommonAttributes implements Attributes {
	/**
	 * @var DateTime
	 */
	private $lastTryTime;

	/**
	 * @var DateTime
	 */
	private $lastRunTime;

	/**
	 * @param DateTime $lastTryTime
	 * @param DateTime $nextRunTime
	 */
	public function __construct(DateTime $lastTryTime, DateTime $nextRunTime) {
		$this->lastTryTime = $lastTryTime;
		$this->lastRunTime = $nextRunTime;
	}

	/**
	 * @return DateTime
	 */
	public function getLastTryTime() {
		return $this->lastTryTime;
	}

	/**
	 * @param DateTime $lastTryTime
	 * @return $this
	 */
	public function setLastTryTime(DateTime $lastTryTime) {
		$this->lastTryTime = $lastTryTime;
		return $this;
	}

	/**
	 * @return DateTime
	 */
	public function getLastRunTime() {
		return $this->lastRunTime;
	}

	/**
	 * @param DateTime $nextRunTime
	 * @return $this
	 */
	public function setLastRunTime(DateTime $nextRunTime) {
		$this->lastRunTime = $nextRunTime;
		return $this;
	}
}