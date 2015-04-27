php-service-dispatcher
======================

[![Build Status](https://travis-ci.org/rkrx/php-service-dispatcher.svg)](https://travis-ci.org/rkrx/php-service-dispatcher)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rkrx/php-service-dispatcher/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rkrx/php-service-dispatcher/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/rkr/php-service-dispatcher/v/stable)](https://packagist.org/packages/rkr/php-service-dispatcher)
[![License](https://poser.pugx.org/rkr/php-service-dispatcher/license)](https://packagist.org/packages/rkr/php-service-dispatcher)

A simple service dispatcher for shell scripts

```PHP
use Kir\Services\Cmd\Dispatcher\AttributeRepositories\SqliteAttributeRepository;
use Kir\Services\Cmd\Dispatcher\Dispatchers\DefaultDispatcher;

require_once 'vendor/autoload.php';

$pdo = new PDO("sqlite:test.db");
$repos = new SqliteAttributeRepository($pdo);
$dispatcher = new DefaultDispatcher($repos);
$dispatcher->register('service1', Dispatcher::ONE_HOUR, function () {
	// Do something
	throw new Exception();
})->register('service2', Dispatcher::ONE_HOUR * 3, function () {
	// Do something
})->run();
```

The example above show the most simple usage of the service dispatcher. Two services get registered. "Service1" and
"Service2". If one service throws an exception, the whole execution stops. Next time, the failed service starts at the
end of the queue. If a service was successfully executed, the timeout schedules the service in this case to 1 hour
(3600 seconds) in the future.

Packagist
---------
[https://packagist.org/packages/rkr/php-service-dispatcher](https://packagist.org/packages/rkr/php-service-dispatcher)