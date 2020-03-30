<?php
namespace FxcmRest;

class SocketIO extends \Evenement\EventEmitter {
	private $loop;
	private $config;
	private $options;
	private $httpClient;
	private $socket;
	private $secKey;
	private $state = ConnectionState::DISCONNECTED;
	private $lastError;
	private $pingTimer;
	
	const WS_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
	
	function __construct(\React\EventLoop\LoopInterface $loop, Config $config) {
		$this->loop = $loop;
		$this->config = $config;
		$this->httpClient = new \React\HttpClient\Client($loop);
	}
	
	function connect() {
		if($this->state === ConnectionState::DISCONNECTED || $this->state === ConnectionState::CONNECTION_ERROR) {
			$this->state = ConnectionState::CONNECTING;
			$this->handshake();
		}
	}
	
	function state() {
		return $this->state;
	}
	
	function disconnect() {
		if($this->state !== ConnectionState::CONNECTED) {
			return;
		}
		$this->socket->close();
		$this->state = ConnectionState::DISCONNECTED;
	}
	
	private function handshake() {
		$request = $this->httpClient->request('GET', "{$this->config->url()}/socket.io/?EIO=3&transport=polling&agent=fxcmrest-php&access_token={$this->config->token()}");
		$request->on('response', [$this, 'handshakeResponse']);
		$request->on('error', function (\Exception $e) {
			$this->state = ConnectionState::CONNECTION_ERROR;
			$this->emit('error', [$e->getMessage()]);
		});
		$request->end();
	}
	
	public function handshakeResponse($response) { // TODO: this must be public for callbacks to work. look into pimpl
		$response->on('data', function ($chunk) use (&$data) {
			$data .= $chunk;
		});
		$response->on('end', function() use (&$data) {
			$first = stripos($data,"{");
			$len =  strripos($data,"}") - $first + 1;
			$data = substr($data, $first, $len);
			$this->options = json_decode($data);
			// var_dump($this->options);
			$connector = new \React\Socket\Connector($this->loop);
			$proto = $this->config->protocol() === Protocol::HTTPS ? "tls://" : "tcp://";
			$connector->connect("{$proto}{$this->config->host()}:{$this->config->port()}")->then([$this, 'upgradeSocket']);
		});
	}
	
	public function upgradeSocket(\React\Socket\ConnectionInterface $connection) {
		$this->socket = $connection;
		$this->socket->once('data', [$this, 'upgradeResponse']);
		$this->socket->on('end', function () {
			// echo 'ended';
		});
		$this->socket->on('error', function (\Exception $e) {
			$this->state = ConnectionState::CONNECTION_ERROR;
			$this->emit('error', [$e->getMessage()]);
		});
		$this->socket->on('close', function () {
			$this->state = ConnectionState::DISCONNECTED;
			$this->emit('disconnected');
		});
		
		$this->secKey = $this->generateSecKey();
		$request = "GET /socket.io/?EIO=3&transport=websocket&sid={$this->options->sid}&access_token={$this->config->token()} HTTP/1.1\r\n"
			. "Host: {$this->config->host()}\r\n"
			. "Upgrade: websocket\r\n"
			. "Connection: Upgrade\r\n"
			. "Sec-WebSocket-Key: {$this->secKey}\r\n"
			. "Sec-WebSocket-Version: 13\r\n"
			. "Connection: keep-alive\r\n"
			. "Accept: */*\r\n"
			. "\r\n";
		$this->socket->write($request);
	}
	
	public function upgradeResponse(string $data) {
		// TODO: check if response is valid
		// ? $array = preg_split('/$\R?^/m', $data);
		
		// echo "upgradeResponse: " . $data . "\n";

		$this->socket->on('data', [$this, 'wsdata']);
		$this->wssend("2probe");
	}
	
	public function wssend(string $data = '') {
		$package = '';
		$frame = 0b1; // fin
		$frame = $frame << 3; // res1, res2, res3
		$frame = $frame << 4 | 0b1; // opcode
		$package .= chr($frame);
		$frame = 1; // mask
		$len = strlen($data);
		$extra = ''; // for len > 125
		if($len > 125) {
			if($len < 256 ** 2) {
				$extra .= chr((int) floor($len / 256));
				$extra .= chr((int) $len % 256);
				$len = 126;
			} else {
				for($i = 7; $i > 0; $i--) {
					$extra .= chr((int) (floor($len / (256 ** $i)) % 256));
				}
				$extra .= chr((int) $len % 256);
				$len = 127;
			}
		}
		$frame = $frame << 7 | $len;
		$package .= chr($frame);
		$package .= $extra;
		$mask = random_bytes(4);
		$l = strlen($data);
		for($i = 0; $i < $l; $i++) {
			$data[$i] = $data[$i] ^ $mask[$i % 4];
		}
		$package .= $mask;
		$package .= $data;
		// echo \hex_dump($package); // debug
		$this->socket->write($package);
	}
	
	public function startPinging() {
		$this->loop->addPeriodicTimer($this->options->pingInterval / 1000, function($timer) {
			$this->wssend("2");
		});
	}
	
	public function wsdata(string $package) {
		$header = 2;
		$fin = (ord($package[0]) & 0b10000000) / 0b10000000 ;
		$opcode = ord($package[0]) & 0b00001111;
		$isMasked = (ord($package[1]) & 0b10000000) / 0b10000000;
		$len = ord($package[1]) & 0b01111111;
		if($len == 126) {
			$header += 2;
			$len = (ord($package[2]) * 256) + ord($package[3]);
		} else if ($len == 127) {
			$header += 8;
			$len = 0;
			for($i = 2; $i < $header; $i++) {
				$len = ($len * 256) + ord($package[$i]);
			}
		}
		if($isMasked) {
			$mask = substr($package, $header, 4);
			$header += 4;
		}
		if($header + $len > strlen($package)) {
			throw new Exception("package shorter than header specified");
		} else {
			if($isMasked) {
				for($i = $header; $i < $len + $header; $i++) {
					$package[$i] = $package[$i] ^ $mask[$i % 4];
				}
			}
			$this->eiodata(substr($package, $header, $len));
			if($header + $len < strlen($package)) {
				$this->wsdata(substr($package, $header + $len));
			}
		}
	}
	
	public function eiodata(string $package) {
		if($package[0] === "4") {
			$this->siodata($package);
		} else if ($package[0] === "3") {
			if($this->state === ConnectionState::CONNECTING) {
				$this->wssend("5");
				$this->startPinging();
			}
		} else {
			// 0, 1, 2, 5, 6 not supported yet
		}
	}
	
	public function siodata(string $package) {
		if($package[1] === "2") {
			$this->emit('data', [substr($package,2)]);
		} else if ($package[1] === "0") {
			$this->state = ConnectionState::CONNECTED;
			$this->emit('connected');
		} else if ($package[1] === "4") {
			$this->emit('error', [substr($package,2)]);
		}
	}
	
	public function generateSecKey() : string {
		$nonce = '';
		for ($i = 0; $i < 16; $i++) {
			$nonce .= chr(mt_rand(0,255));
		}
		return base64_encode($nonce);
	}
	
	public function checkSecAccept(string $accept) : bool {
		if ($accept === base64_encode(sha1($this->secKey . $this->WS_GUID, true))) {
			return true;
		} else {
			return false;
		}
	}
	
	public function socketID() : string {
		if($this->state === ConnectionState::CONNECTED) {
			return $this->options->sid;
		} else {
			return "";
		}
	}
}
?>
