<?php
namespace FxcmRest;

class ConnectionState extends ThirdParty\BasicEnum {
	const DISCONNECTED = 'disconnected';
	const CONNECTING = 'connecting';
	const CONNECTION_ERROR = 'connection error';
	const CONNECTED = 'connected';
	const DISCONNECTING = 'disconnecting';
}
?>