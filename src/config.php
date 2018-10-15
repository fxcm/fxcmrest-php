<?php
namespace FxcmRest;

class Config {
	private $config;
	
	function __construct(array $arguments) {
		$defaults = [
			'protocol' => 'https',
			'host' => '',
			'port' => 0,
			'path' => '',
			'token' => '',
			'autoreconnect' => false,
		];
		$this->config = Functions::checkParams($defaults, $arguments);
		
		if (!Protocol::isValidValue($this->protocol())) {
			throw new \Exception("Config error: protocol not supported");
		}
		if (!$this->host()) {
			throw new \Exception("Config error: host is not defined");
		}
		if (1 > $this->port() || $this->port() > 65535) {
			throw new \Exception("Config error: port must be between 1 and 65535");
		}
		$this->config['path'] = rtrim($this->path(), "/");
		if ($this->path() && substr($this->path(), 0, 1) !== '/') {
			$this->config['path'] = "/{$this->path()}";
		}
		if (!filter_var($this->url(),FILTER_VALIDATE_URL)) {
			throw new \Exception("Config error: url is not valid {$url}");
		}
		if (strlen($this->token()) != 40 || !preg_match("~^[a-fA-F0-9]+$~",$this->token())) {
			throw new \Exception("Config error: token must have a length of 40 hexadecimal characters");
		}
	}
	
	function protocol() : string {
		return $this->config['protocol'];
	}
	function host() : string {
		return $this->config['host'];
	}
	function port() : int {
		return $this->config['port'];
	}
	function path() : string {
		return $this->config['path'];
	}
	function token() : string {
		return $this->config['token'];
	}
	function url() : string {
		return "{$this->protocol()}://{$this->host()}:{$this->port()}{$this->path()}";
	}
	function autoreconnect() : bool {
		return $this->config['autoreconnect'];
	}
}
?>