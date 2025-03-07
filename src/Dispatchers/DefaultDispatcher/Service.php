<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

use Stringable;

class Service implements Stringable {
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
