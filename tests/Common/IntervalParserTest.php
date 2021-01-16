<?php

namespace Kir\Services\Cmd\Dispatcher\Common;

use PHPUnit\Framework\TestCase;

class IntervalParserTest extends TestCase {
	public function testParseNumericInterval() {
		$result = IntervalParser::getNext(5400, date_create_immutable('2000-01-01 00:00:00'));
		self::assertEquals('2000-01-01 01:30:00', $result->format('Y-m-d H:i:s'));
	}
	
	public function testParseHourAndMinute() {
		$result = IntervalParser::getNext('01:30', date_create_immutable('2000-01-01 00:00:00'));
		self::assertEquals('2000-01-01 01:30:00', $result->format('Y-m-d H:i:s'));
	}
	
	public function testCronExpression() {
		$result = IntervalParser::getNext('0 3 * * *', date_create_immutable('2000-01-01 00:00:00'));
		self::assertEquals('2000-01-01 03:00:00', $result->format('Y-m-d H:i:s'));
	}
}
