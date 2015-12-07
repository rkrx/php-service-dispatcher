<?php
namespace Kir\Services\Cmd\Dispatcher\Common;

use Exception;

class IntervalParser {
	/**
	 * @param string|int $interval
	 * @return int
	 */
	public static function parse($interval) {
		if(is_array($interval)) {
			$result = [];
			foreach($interval as $intervalStr) {
				$result[] = self::parseString($intervalStr);
			}
			return min($result);
		} else {
			return self::parseString((string) $interval);
		}
	}

	/**
	 * @param int|string $interval
	 * @return int
	 */
	private static function parseString($interval) {
		if(preg_match('/^\\d+$/', $interval)) {
			return $interval;
		}
		if(preg_match('/^(\\d{1,2}|\\*):(\\d{1,2}|\\*)(?::(\\d{1,2}|\\*))?$/', $interval, $matches)) {
			$matches[] = 0;
			list($hours, $minutes, $seconds) = array_slice($matches, 1);
			$possibleDates = [
				sprintf('today %02d:%02d:%02d', $hours, $minutes, $seconds),
				sprintf('tomorrow %02d:%02d:%02d', $hours, $minutes, $seconds),
			];
			return self::nearst($possibleDates);
		}
	}

	/**
	 * @param array $possibleDates
	 * @return int
	 * @throws Exception
	 */
	private static function nearst(array $possibleDates) {
		foreach($possibleDates as $possibleDate) {
			$time = strtotime($possibleDate);
			if($time > time()) {
				return $time - time();
			}
		}
		throw new Exception('No alternative lays in the future');
	}
}
