<?php
namespace FxcmRest\ThirdParty;
use \ReflectionClass;

/**
 * This Source Code Form is subject to the terms of the
 * Attribution-ShareAlike 3.0 Unported (CC BY-SA 3.0).
 * If a copy of the license was not distrubuted with
 * this file, You can obtain one at
 * https://creativecommons.org/licenses/by-sa/3.0/
 *
 * BasicEnum, Copyright (c) Brian Cline
 * https://stackoverflow.com/a/254543
 */
 
abstract class BasicEnum {
	private static $constCacheArray = NULL;
	
	private static function getConstants() {
		if (self::$constCacheArray == NULL) {
			self::$constCacheArray = [];
		}
		$calledClass = get_called_class();
		if (!array_key_exists($calledClass, self::$constCacheArray)) {
			$reflect = new ReflectionClass($calledClass);
			self::$constCacheArray[$calledClass] = $reflect->getConstants();
		}
		return self::$constCacheArray[$calledClass];
	}
	
	public static function isValidName($name, $strict = false) {
		$constants = self::getConstants();
	
		if ($strict) {
			return array_key_exists($name, $constants);
		}
	
		$keys = array_map('strtolower', array_keys($constants));
		return in_array(strtolower($name), $keys);
	}
	
	public static function isValidValue($value, $strict = true) {
		$values = array_values(self::getConstants());
		return in_array($value, $values, $strict);
	}
}
?>