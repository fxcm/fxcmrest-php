<?php
namespace FxcmRest;

class HttpMethod extends ThirdParty\BasicEnum {
	const GET = 'GET';
	const POST = 'POST';
}

class Protocol extends ThirdParty\BasicEnum {
	const HTTP = 'http';
	const HTTPS = 'https';
}

class ConnectionState extends ThirdParty\BasicEnum {
	const DISCONNECTED = 'disconnected';
	const CONNECTING = 'connecting';
	const CONNECTION_ERROR = 'connection error';
	const CONNECTED = 'connected';
	const DISCONNECTING = 'disconnecting';
}

function checkParams($default, $input) : array {
	$diff = array_diff_key($input,$default);
	if ($diff) {
		throw new \Exception("Parameter error: unknown parameter(s): ". implode(", ",array_keys($diff)));
	}
	return array_merge($default, $input);
}
?>