<?php

namespace helpers;

class Utility {
	
	protected $sandbox = NULL;
	
	public function __construct(&$sandbox) {
		$this->sandbox = &$sandbox;
	}
	
	public function createURL($object, $ID, $name){
		$text = $this->createURLComponent($name);
		return "/{$object}/{$text}-{$ID}";
	}
	
	public function createURLComponent($name){
		$text = strtolower(trim(preg_replace('~[^\\pL\d]+~u', '-', $name), '-'));
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		return preg_replace('~[^-\w]+~', '', $text);		
	}
	
}
