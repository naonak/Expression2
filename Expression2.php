<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 *
 * @author Fabien Evain
 * @dependencies None
 * 
 * todo : handling "!important"
 * 
 **/
class Expression2 extends Plugins {

	static $numericProperties = array(
		'[-_A-Za-z\*]*(?<!border-)width',
		'(?<!border-)bottom',
		'(?<!border-)left',
		'(?<!border-)right',
		'(?<!border-)top',
		'[-_A-Za-z\*]*height',
		'[-_A-Za-z\*]*color',
		'font-size',
		'font-weight',
		'margin-[-_A-Za-z\*]+',
		'padding-[-_A-Za-z\*]+',
		'border-spacing',
		'text-indent',
		'vertical-align',
		'word-spacing',
		'line-height',
		'letter-spacing',
		'z-index', // no unit
	);

	static $units = array('%', 'in', 'cm', 'mm', 'em', 'ex', 'pt', 'pc', 'px');

	public static function post_process() {
		CSS::$css = self::parse_expressions();
	}

	public static function parse_expressions($css = "")
	{
		# If theres no css string given, use the master css
		if($css == "") $css = CSS::$css;
				
		foreach(self::$numericProperties as $property) {
			preg_match_all('/[^\s;\}\{\-_A-Za-z\*]*(?P<property_name>'.$property.')\s*\:\s*(?P<property_value>.*?)\s*\;/', $css, $matches);
			for($i=0;$i < count($matches[0]); $i++) {
				if(match('/(\#\[[\'\"]?([^]]*?)[\'\"]?\])/', $matches['property_value'][$i])) {
					continue;
				}
				$result = self::operate($matches['property_value'][$i], $matches['property_name'][$i]);
				if($result !== false) {
					$css = str_replace($matches[0][$i], "{$matches['property_name'][$i]}: $result;", $css);
				}
			}
		}

		preg_match_all('/[^a-zA-Z]\((?P<to_operate>[^\)]*?)\)/', $css, $matches);
		for($i=0;$i < count($matches[0]); $i++) {
			$result = self::operate($matches['to_operate'][$i], null, true);
			if($result !== false) {
				$css = str_replace($matches[0][$i], " " . $result, $css);
			}
		}
		return $css;
	}
	
	static function substitue($regex, $text, $firstCaptureOnly = false) {
		preg_match_all($regex, $text, $matches);
		if (empty($matches[0])) {
			return array($text, array(), array());
		}
		$index = $firstCaptureOnly?1:0;
		$text = str_replace('%', '%%', $text);
		$text = preg_replace($regex, '%s', $text);
		return array($text, $matches[$index]);
	}
	
	static function operate($operation, $property = null, $force = false) {

		$value = $operation;
		if($operation == 'auto' || $operation == 'inherit') {
			return false;
		}
		list($operation, $hex) = self::substitue('/#[\w_]{3,6}/', $operation);
			
		$useHex = (bool) count($hex);
			
		foreach($hex as &$h) {
			$h = substr($h, 1);
			if(strlen($h) === 3) {
				$h = str_split($h);
				$_h = null;
				foreach($h as $v) {
					$_h .= $v.$v;
				}
				$h = $_h;
			}
			$h = "0x$h";
			$eval = "\$h = $h;";
			$h = hexdec($h);
		}

		if($hex) {
			$operation = vsprintf($operation, $hex);
		}

		$unit = null;
		if(preg_match('/[\d\.]+('.implode('|',self::$units).')/', $operation, $m)) {
			$unit = $m[1];
			$operation = str_replace($unit, '', $operation);
		}

		if(!$unit && !$useHex && $property != 'z-index' && !$force) {
			return false;
		}

		$result = eval("return $operation;");
		if($result === false) {
			return false;
		}

		if($useHex) {
			$operation = '#'.str_pad(dechex($result), 6, '0', STR_PAD_LEFT);
		} else {
			$operation = $result.$unit;
		}
		return $operation;
	}

}
