php-service-dispatcher
======================

[![Build Status](https://travis-ci.org/rkrx/php-service-dispatcher.svg)](https://travis-ci.org/rkrx/php-service-dispatcher)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rkrx/php-service-dispatcher/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rkrx/php-service-dispatcher/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/rkr/service-dispatcher/v/stable)](https://packagist.org/packages/rkr/service-dispatcher)
[![License](https://poser.pugx.org/rkr/service-dispatcher/license)](https://packagist.org/packages/rkr/service-dispatcher)

## Common usage using SQLite

A simple service dispatcher for shell scripts. The intent is to run a php shell script every minute and let the script decide, what to run at what time...

```PHP
use Kir\Services\Cmd\Dispatcher\Dispatcher;

require __DIR__ . '/vendor/autoload.php';

use Kir\Services\Cmd\Dispatcher\ServiceDispatcherBuilder;

$dispatcher = ServiceDispatcherBuilder::withSQLite(__DIR__.'/services.db');

$dispatcher->register('service1', Dispatcher::ONE_HOUR, function () {
	// Do something
	throw new Exception();
});

$dispatcher->register('service2', Dispatcher::ONE_HOUR * 3, function () {
	// Do something
});

$dispatcher->run();
```

The example above show the most simple usage of the service dispatcher. Two services get registered. "Service1" and
"Service2". If one service throws an exception, the whole execution stops. Next time, the failed service starts at the
end of the queue. If a service was successfully executed, the timeout schedules the service in this case to 1 hour
(3600 seconds) in the future.

## MySQL-Specific settings:

```PHP
use Kir\Services\Cmd\Dispatcher\ServiceDispatcherBuilder;

require __DIR__ . '/vendor/autoload.php';

/* ...  */

$dispatcher = ServiceDispatcherBuilder::withMySQL($pdo)
->useLocking(true)
->setLockPrefix('my-app:');

$dispatcher->register(/*...*/);

/* ...  */
```
