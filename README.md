# FxcmRest
FxcmRest is a library for event-driven trading with FXCM over RestAPI using ReactPHP.

## Requirements
 - PHP 7.0.2+

## Installation
The recommended way to install FxcmRest is through [Composer](https://getcomposer.org/).

This command will install the latest stable version:
```bash
$ composer require fxcm/fxcmrest
```

## Usage
Main class of the library is \FxcmRest\FxcmRest. It must be instantiated with two objects:
 - `\React\EventLoop\LoopInterface`
 - `\FxcmRest\Config`

Configuration class \FxcmRest\Config must be instantiated with an array containing at least the two following parameters:
 - `host`
 - `token`

### Configuration Parameters
 - `protocol` - either `\FxcmRest\Protocol::HTTPS` (default) or `\FxcmRest\Protocol::HTTP`
 - `host` - either `"api.fxcm.com"` for Real accounts or `"api-demo.fxcm.com"` for Demo accounts
 - `port` - port number. `443` default
 - `token` - 40 char hexadecimal string

### Functions
 ```php
 connect() : null
 ```
 Opens a connection to the server. When connection is complete, `connected` signal will be emitted.
 ```php
 disconnect() : null
 ```
 Disconnects from the server. When disconnection is complete, `disconnected` signal will be emitted. 
 ```php
 socketID() : string
 ```
 If connected to the server, returns a string representing the socketID. If not connected, returns an empty string.
 ```php
 request(\FxcmRest\HttpMethod $method, string $path, array $arguments, callable $callback) : null
 ```
 Sends a http request to the server. When request is completed, $callback will be called with two parameters:
 - `int` representing HTTP status code. 200 for OK
 - `string` representing server answer body
 ```php
 on(string $signalName, callable $callback) : null
 ```
 Registers a $callback for a signal of $signalName. For a list of signals and parameters that are passed with them please see Signals section.
 
### Signals

 `connected` - Emitted when connection sequence is complete. After this socketID is valid and requests can be sent to the server. No parameters are passed.
 
 `disconnected` - Emitted when connection to the server is closed. No parameters are passed.
 
 `error` - Emitted on errors. Passes error description as string.
 
 `[Offer,OpenPosition,ClosedPosition,Account,Summary,Properties]` - Emmited on trading table changes. Passes table update contents as JSON string. Requires subscription through `/trading/subscribe`
 
 `(EUR/USD,EUR/GBP,...)` - Emmited on price update. Passes the price update as a JSON string. Requires subscription through `/subscribe`.

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
