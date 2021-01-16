<?php
namespace Kir\Services\Cmd\Dispatcher\Common;

use Cron\CronExpression;
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
	 * @param $interval
	 * @param DateTimeInterface|null $now
	 * @return Generator|DateTimeImmutable
	 */
	private static function parse($interval, DateTimeInterface $now) {
		if(is_array($interval)) {
			foreach($interval as $inner) {
				yield from self::parse($inner, $now);
			}
		} elseif(ctype_digit($interval)) {
			yield self::parseInt($interval, $now);
		} else {
			yield self::parseString($interval, $now);
		}
	}
	
	/**
	 * @param int $interval
	 * @param DateTimeInterface $now
	 * @return DateTimeImmutable
	 */
	private static function parseInt(int $interval, DateTimeInterface $now): DateTimeImmutable {
		return $now->modify("+{$interval} second");
	}
	
	/**
	 * @param string $interval
	 * @param DateTimeInterface $now
	 * @return DateTimeInterface
	 */
	private static function parseString(string $interval, DateTimeInterface $now): DateTimeInterface {
		if(preg_match('/^(\\d{1,2}|\\*):(\\d{1,2}|\\*)(?::(\\d{1,2}|\\*))?$/', $interval, $matches)) {
			$matches[] = 0;
			[$hours, $minutes, $seconds] = array_slice($matches, 1);
			$today = date_create_immutable($now->format('c'))->setTime((int) $hours, (int) $minutes, (int) $seconds);
			$possibleDates = [
				$today,
				$today->modify('+24 hour')
			];
			return self::nearst($possibleDates, $now);
		}
		// Expect input to be a cron-expression
		$expr = new CronExpression($interval);
		$dt = $expr->getNextRunDate($now);
		return new DateTimeImmutable($dt->format('c'));
	}
	
	/**
	 * @param array $possibleDates
	 * @param DateTimeInterface $now
	 * @return DateTimeInterface
	 */
	private static function nearst(array $possibleDates, DateTimeInterface $now) {
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
