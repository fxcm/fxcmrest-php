<?php
namespace FxcmRest;

class FxcmRest extends \Evenement\EventEmitter {
	private $loop;
	private $httpClient;
	private $config;
	private $socketIO;
	
	function __construct(\React\EventLoop\LoopInterface $loop, Config $config) {
		$this->loop = $loop;
		$this->config = $config;
		$this->httpClient = new \React\HttpClient\Client($this->loop);
		$this->socketIO = new SocketIO($this->loop, $this->config);
		$this->socketIO->on('connected', function() {
			$this->emit('connected');
		});
		$this->socketIO->on('data', function($data) {
			$json = json_decode($data);
			$this->emit($json[0], [$json[1]]);
		});
		$this->socketIO->on('error', function($e) {
			$this->emit('error', [$e]);
		});
		$this->socketIO->on('disconnected', function() {
			$this->emit('disconnected');
		});
	}
	
	function connect() {
		$this->socketIO->connect();
	}
	
	function disconnect() {
		$this->socketIO->disconnect();
	}
	
	function socketID() : string {
		return $this->socketIO->socketID();
	}
	
	function request(string $method, string $path, array $arguments, callable $callback) {
		$data = '';
		$url = $this->config->url() . $path;
		$arguments = http_build_query($arguments);
		$headers =[
			'User-Agent' => 'request',
			'Accept' => 'application/json',
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Authorization' => "Bearer {$this->socketIO->socketID()}{$this->config->token()}"
		];
		$end = "";
		if ($method === HttpMethod::POST) {
			$headers['Transfer-Encoding'] = 'chunked';
			$arglen = dechex(strlen($arguments));
			$end = "{$arglen}\r\n{$arguments}\r\n0\r\n\r\n";
		} else if ($method === HttpMethod::GET && $arguments) {
			$url .= "?" . $arguments;
		}
		$request = $this->httpClient->request($method, $url, $headers, '1.1');
		$request->on('response', function ($response) use ($data, $callback) {
			$response->on('data', function ($chunk) use (&$data) {
				$data .= $chunk;
			});
			$response->on('end', function () use (&$data, $callback, $response) {
				$callback($response->getCode(), $data);
			});
		});
		$request->on('error', function (\Exception $e) use ($callback) {
			$callback(0,$e);
		});
		$request->end($end);
	}
}
?>