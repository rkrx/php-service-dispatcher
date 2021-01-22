<?php
namespace Kir\Services\Cmd\Dispatcher\Common;

use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use RuntimeException;
use Throwable;

class IntervalParser {
	/**
	 * @param string|int|array $interval
	 * @param DateTimeInterface|null $now
	 * @return DateTimeImmutable
	 */
	public static function getNext($interval, DateTimeInterface $now = null): DateTimeImmutable {
		if($now === null) {
			try {
				$now = new DateTimeImmutable();
			} catch (Throwable $e) {
				throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
			}
		} else {
			$now = DateTimeHelper::createImmutable($now);
		}
		$result = null;
		foreach(self::parse($interval, $now) as $date) {
			if($result === null) {
				$result = $date;
			} elseif($date < $result) {
				$result = $date;
			}
		}
		return $result;
	}
	
	/**
	 * @param string|array $interval
	 * @param DateTimeImmutable $now
	 * @return Generator|DateTimeImmutable[]
	 */
	private static function parse($interval, DateTimeImmutable $now) {
		if(is_array($interval)) {
			foreach($interval as $inner) {
				yield from self::parse($inner, $now);
			}
		} elseif(preg_match('/^\\d+$/', $interval)) {
			yield self::parseInt($interval, $now);
		} else {
			yield self::parseString($interval, $now);
		}
	}
	
	/**
	 * @param int $interval
	 * @param DateTimeImmutable $now
	 * @return DateTimeImmutable
	 */
	private static function parseInt(int $interval, DateTimeImmutable $now): DateTimeImmutable {
		return $now->modify("+{$interval} second");
	}
	
	/**
	 * @param string $interval
	 * @param DateTimeImmutable $now
	 * @return DateTimeImmutable
	 */
	private static function parseString(string $interval, DateTimeImmutable $now): DateTimeImmutable {
		if(preg_match('/^(\\d{1,2}|\\*):(\\d{1,2}|\\*)(?::(\\d{1,2}|\\*))?$/', $interval, $matches)) {
			$matches[] = 0;
			[$hours, $minutes, $seconds] = array_slice($matches, 1);
			$today = DateTimeHelper::createImmutable($now)->setTime((int) $hours, (int) $minutes, (int) $seconds);
			$possibleDates = [
				$today,
				$today->modify('+24 hour')
			];
			return self::nearst($possibleDates, $now);
		}
		// Expect input to be a cron-expression
		return DateTimeHelper::getNextRunDateFromCronExpression($interval, $now);
	}
	
	/**
	 * @param array $possibleDates
	 * @param DateTimeImmutable $now
	 * @return DateTimeImmutable
	 */
	private static function nearst(array $possibleDates, DateTimeImmutable $now) {
		$current = null;
		foreach($possibleDates as $possibleDate) {
			if($now > $possibleDate) { // The current date is in the past
				continue;
			}
			if($current === null || $possibleDate < $current) {
				$current = $possibleDate;
			}
		}
		if($current !== null) {
			return $current;
		}
		throw new RuntimeException('No alternative lays in the future');
	}
}
