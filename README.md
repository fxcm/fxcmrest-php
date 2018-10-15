# FxcmRest-php
FxcmRest is a library for event-driven trading with FXCM over RestAPI using ReactPHP.

## Requirements
 - PHP 7.0.2+ 

## Installation
The recommended way to install FxcmRest is through Composer.

This command will install the latest stable version:
```bash
$ composer require fxcm/fxcmrest
```

## Usage
Main class of the library is \FxcmRest\FxcmRest. It must be instantiated with two objects:
 - \React\EventLoop\LoopInterface
 - \FxcmRest\Config

Configuration class \FxcmRest\Config must be instantiated with an array containing at least the two following parameters:
 - host
 - token

### Configuration Parameters
 - protocol - either (default) \FxcmRest\Protocol::HTTPS or \FxcmRest\Protocol::HTTP
 - host - either 'api.fxcm.com' for Real accounts or 'api-demo.fxcm.com' for Demo accounts
 - port - port number. 443 default
 - token - 40 char hexadecimal string

### Functions
 - connect() : null
 - disconnect() : null
 - socketID() : string
 - request(\FxcmRest\HttpMethod $method, string $path, array $arguments, callable $callback) : null
 - on(string $signalName, callable $callback) : null
 
### Signals
 - connected
 - disconnected
 - error
 - (trading tables) - one of ()
 - (price updates)

## Sample Code
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$config = new \FxcmRest\Config([
	'host' => 'api-demo.fxcm.com',
	'token' => 'YOUR_TOKEN',
]);

$counter = 0;
$rest = new FxcmRest\FxcmRest($loop, $config);
$rest->on('connected', function() use ($rest,&$counter) {
	$rest->request('POST', '/subscribe',
		['pairs' => 'EUR/USD'],
		function($code, $data) use ($rest,&$counter) {
			if($code === 200) {
				$rest->on('EUR/USD', function($data) use ($rest,&$counter) {
					echo "price update: {$data}\n";
					$counter++;
					if($counter === 5){
						$rest->disconnect();
					}
				});
			}
		}
	);
});
$rest->on('error', function($e) use ($loop) {
	echo "socket error: {$e}\n";
	$loop->stop();
});
$rest->on('disconnected', function() use ($loop) {
	echo "FxcmRest disconnected\n";
	$loop->stop();
});
$rest->connect();

$loop->run();
?>
```
