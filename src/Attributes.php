<?php
namespace Kir\Services\Cmd\Dispatcher;

use DateTime;

interface Attributes {
	/**
	 * @return DateTime
	 */
	public function getLastTryTime();

	/**
	 * @param DateTime $lastRunTime
	 * @return void
	 */
	public function setLastTryTime(DateTime $lastRunTime);

	/**
	 * @return DateTime
	 */
	public function getLastRunTime();

	/**
	 * @param DateTime $nextRunTime
	 * @return void
	 */
	public function setLastRunTime(DateTime $nextRunTime);
}