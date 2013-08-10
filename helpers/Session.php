<?php

namespace helpers;

class Session {
	
	protected $hash = NULL;
	
	protected $seed = "5yeQmfaNRp7Bs46V";

	public function __construct() {
		$this->generateHash();
		$this->setHash();
	}
	
	public function write($key, $value) {
		$_SESSION[$key] = $value;
	}
	
	public function read($key) {
		if(!array_key_exists($key, $_SESSION) || !$this->verifyHash()) {
			return NULL;
		} else {
			return $_SESSION[$key];
		}
	}
	
	protected function verifyHash(){
		if(is_null($_SESSION["hash"])) {
			return false;
		} else {
			if($this->getHash() == $_SESSION["hash"]) {
				return true;
			} else {
				return false;
			}
		}
	}
	
	protected function generateHash(){
		$this->hash = isset($_SERVER['HTTP_USER_AGENT']) ? md5($_SERVER['HTTP_USER_AGENT']) : md5($_SERVER['REMOTE_ADDR']);
	}

	protected function setHash(){
		if(array_key_exists("hash", $_SESSION)) return;
		$this->write("hash", $this->hash);
	}
	
	public function getHash(){
		return $this->hash;
	}
	
	public function purge(){
		$_SESSION = array();
		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time() - 42000, '/');
		}
		session_destroy();
	}
		
}

?>