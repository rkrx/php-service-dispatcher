php-service-dispatcher
======================

[![Build Status](https://travis-ci.org/rkrx/php-service-dispatcher.svg)](https://travis-ci.org/rkrx/php-service-dispatcher)

A simple service dispatcher for shell scripts

```PHP
use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

require_once 'vendor/autoload.php';

$pdo = new PDO("sqlite:test.db");
$repos = new SqliteAttributeRepository($pdo);
$dispatcher = new DefaultDispatcher($repos);
$dispatcher->register('service1', 3600, function () {
	// Do something
	throw new Exception();
})->register('service2', 3600, function () {
	// Do something
})->run();
```

The example above show the most simple usage of the service dispatcher. Two services get registered. "Service1" and
"Service2". If one service throws an exception, the whole execution stops. Next time, the failed service starts at the
end of the queue.

Packagist
---------
[https://packagist.org/packages/rkr/php-service-dispatcher](https://packagist.org/packages/rkr/php-service-dispatcher)