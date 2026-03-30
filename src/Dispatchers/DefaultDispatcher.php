<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Ioc\MethodInvoker;
use Kir\Services\Cmd\Dispatcher\AttributeRepository;
use Kir\Services\Cmd\Dispatcher\Common\IntervalParser;
use Kir\Services\Cmd\Dispatcher\Dispatcher;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * @phpstan-import-type TInterval from IntervalParser
 */
class DefaultDispatcher implements Dispatcher {
	/** @var array<string, object{key: string, fn: callable(mixed ...$arg): mixed, interval: TInterval}> */
	private array $services = [];
	/** @var array<string, callable[]> */
	private array $listeners = [];
	
	/**
	 * @param AttributeRepository $attributeRepository
	 * @param MethodInvoker|null $methodInvoker
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		private readonly AttributeRepository $attributeRepository,
		private readonly ?MethodInvoker $methodInvoker = null,
		private readonly ?LoggerInterface $logger = null
	) {}

	/**
	 * @param string $key
	 * @param TInterval $interval
	 * @param callable $callable
	 * @return $this
	 */
	public function register(string $key, $interval, callable $callable) {
		$this->attributeRepository->register($key);
		$this->services[$key] = (object) [
			'fn' => $callable,
			'key' => $key,
			'interval' => $interval
		];
		return $this;
	}

	/**
	 * @param string $event
	 * @param callable $fn
	 * @return $this
	 */
	public function on(string $event, callable $fn) {
		if(!array_key_exists($event, $this->listeners)) {
			$this->listeners[$event] = [];
		}
		$this->listeners[$event][] = $fn;
		return $this;
	}

	/**
	 * @param DateTimeInterface|null $now
	 * @return int Number of successfully executed services
	 */
	public function run(?DateTimeInterface $now = null): int {
		/** @var DateTimeInterface $dt */
		$dt = $now ?? new DateTimeImmutable();
		return $this->attributeRepository->lockAndIterateServices($dt, function (string $serviceKey) {
			if(!array_key_exists($serviceKey, $this->services)) {
				return;
			}
			$serviceData = $this->services[$serviceKey];
			$eventParams = [
				'serviceName' => $serviceData->key,
				'interval' => $serviceData->interval
			];
			try {
				$this->fireEvent('service-start', $eventParams);
				$timer = microtime(true);
				$this->attributeRepository->setLastTryDate($serviceData->key, new DateTimeImmutable());
				if($this->methodInvoker !== null) {
					$result = $this->methodInvoker->invoke($serviceData->fn, $eventParams);
				} else {
					$result = call_user_func($serviceData->fn, $eventParams);
				}
				$this->attributeRepository->setLastRunDate($serviceData->key, new DateTimeImmutable());
				$nextRunDate = IntervalParser::getNext($serviceData->interval);
				$this->attributeRepository->setNextRunDate($serviceData->key, $nextRunDate);
				if($result !== false) {
					$eventParams['executionTime'] = microtime(true) - $timer;
					$this->fireEvent('service-success', $eventParams);
				}
			} catch (Throwable $e) {
				$eventParams['exception'] = $e;
				$this->fireEvent('service-failure', $eventParams);
				if($this->logger !== null) {
					$this->logger->critical("{$eventParams['serviceName']}: {$e->getMessage()}", ['exception' => $e]);
				} else {
					throw new RuntimeException("{$eventParams['serviceName']}: {$e->getMessage()}", (int) $e->getCode(), $e);
				}
			}
		});
	}

	/**
	 * @param string $event
	 * @param array<string, mixed> $params
	 */
	private function fireEvent(string $event, array $params): void {
		if(array_key_exists($event, $this->listeners)) {
			try {
				foreach($this->listeners[$event] as $listener) {
					if($this->methodInvoker !== null) {
						$this->methodInvoker->invoke($listener, $params);
					} else {
						call_user_func($listener, $params['serviceName'] ?? null);
					}
				}
			} catch (Exception $e) {
				// Supress exceptions emitted by events
				$this->logger?->critical($e->getMessage(), ['exception' => $e]);
			}
		}
	}
}
