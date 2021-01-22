<?php
namespace Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

class Service {
	/** @var string */
	private $key;
	
	/**
	 * @param string $key
	 */
	public function __construct(string $key) {
		$this->key = $key;
	}
	
	/**
	 * @return string
	 */
	public function getKey(): string {
		return $this->key;
	}
	
	/**
	 * @return string
	 */
	public function __toString(): string {
		return $this->key;
	}
}
