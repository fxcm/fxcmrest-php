<?php
namespace FxcmRest\ThirdParty;
use \InvalidArgumentException;

/**
 * This Source Code Form is subject to the terms of the
 * Attribution-ShareAlike 3.0 Unported (CC BY-SA 3.0).
 * If a copy of the license was not distrubuted with
 * this file, You can obtain one at
 * https://creativecommons.org/licenses/by-sa/3.0/
 *
 * hex_dump, Copyright (c) Internal Server Error
 * https://stackoverflow.com/a/34279537
 */

 /**
* Dumps a string into a traditional hex dump for programmers,
* in a format similar to the output of the BSD command hexdump -C file.
* The default result is a string.
* Supported options:
* <pre>
*   line_sep        - line seperator char, default = "\n"
*   bytes_per_line  - default = 16
*   pad_char        - character to replace non-readble characters with, default = '.'
* </pre>
*
* @param string $string
* @param array $options
* @param string|array
*/
function hex_dump($string, array $options = null) {
	if (!is_scalar($string)) {
		throw new InvalidArgumentException('$string argument must be a string');
	}
	if (!is_array($options)) {
		$options = array();
	}
	$line_sep       = isset($options['line_sep'])   ? $options['line_sep']          : "\n";
	$bytes_per_line = @$options['bytes_per_line']   ? $options['bytes_per_line']    : 16;
	$pad_char       = isset($options['pad_char'])   ? $options['pad_char']          : '.'; # padding for non-readable characters
	
	$text_lines = str_split($string, $bytes_per_line);
	$hex_lines  = str_split(bin2hex($string), $bytes_per_line * 2);
	
	$offset = 0;
	$output = array();
	$bytes_per_line_div_2 = (int)($bytes_per_line / 2);
	foreach ($hex_lines as $i => $hex_line) {
		$text_line = $text_lines[$i];
		$output []=
			sprintf('%08X',$offset) . '  ' .
			str_pad(
				strlen($text_line) > $bytes_per_line_div_2
				?
					implode(' ', str_split(substr($hex_line,0,$bytes_per_line),2)) . '  ' .
					implode(' ', str_split(substr($hex_line,$bytes_per_line),2))
				:
				implode(' ', str_split($hex_line,2))
			, $bytes_per_line * 3) .
			'  |' . preg_replace('/[^\x20-\x7E]/', $pad_char, $text_line) . '|';
		$offset += $bytes_per_line;
	}
	$output []= sprintf('%08X', strlen($string));
	return @$options['want_array'] ? $output : join($line_sep, $output) . $line_sep;
}
?>