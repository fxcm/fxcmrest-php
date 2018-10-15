<?php
namespace FxcmRest;

class Functions {
	public static function checkParams($default, $input) : array {
		$diff = array_diff_key($input,$default);
		if ($diff) {
			throw new \Exception("Parameter error: unknown parameter(s): ". implode(", ",array_keys($diff)));
		}
		return array_merge($default, $input);
	}
}
?>