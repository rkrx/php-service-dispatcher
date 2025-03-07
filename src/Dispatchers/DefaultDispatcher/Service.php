<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

class Service {
	public function __construct(
		public readonly string $key
	) {}
	
	/**
	 * @return string
	 */
	public function __toString(): string {
		return $this->key;
	}
}
