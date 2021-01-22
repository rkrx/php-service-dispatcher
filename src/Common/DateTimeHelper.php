<?php
namespace Kir\Services\Cmd\Dispatcher\Common;

use Cron\CronExpression;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use RuntimeException;
use Throwable;

abstract class DateTimeHelper {
	/**
	 * @param string|DateTimeInterface|null $init
	 * @return DateTime
	 */
	public static function create($init = null): DateTime {
		try {
			if($init instanceof DateTimeInterface) {
				return new DateTime($init->format('c'));
			}
			return new DateTime($init);
		} catch (Throwable $e) {
			throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}
	
	/**
	 * @param string|DateTimeInterface|null $init
	 * @return DateTimeImmutable
	 */
	public static function createImmutable($init = null): DateTimeImmutable {
		try {
			if($init instanceof DateTimeImmutable) {
				return $init;
			}
			if($init instanceof DateTimeInterface) {
				return new DateTimeImmutable($init->format('c'));
			}
			return new DateTimeImmutable($init);
		} catch (Exception $e) {
			throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}
	
	/**
	 * @param string $interval
	 * @param DateTimeInterface $now
	 * @return DateTimeImmutable
	 */
	public static function getNextRunDateFromCronExpression(string $interval, DateTimeInterface $now): DateTimeImmutable {
		try {
			$expr = new CronExpression($interval);
			$dt = $expr->getNextRunDate($now);
			return self::createImmutable($dt);
		} catch (Exception $e) {
			throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}
}