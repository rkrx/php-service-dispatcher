<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

use DateTime;
use DateTimeZone;

class ServiceSorter {
	/**
	 * @param Service[] $services
	 * @return Service[]
	 */
	public function sort(array $services) {
		usort($services, function (Service $a, Service $b) {
			return $this->dateDiff($b->getAttributes()->getLastTryTime(), $a->getAttributes()->getLastTryTime());
		});
		return $services;
	}

	/**
	 * @param DateTime $a
	 * @param DateTime $b
	 * @return int
	 */
	private function dateDiff(DateTime $a, DateTime $b) {
		$aC = clone $a;
		$bC = clone $b;
		$aC->setTimezone(new DateTimeZone('UTC'));
		$bC->setTimezone(new DateTimeZone('UTC'));
		return $b->format('U') - $a->format('U');
	}
}