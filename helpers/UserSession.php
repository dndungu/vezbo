<?php

namespace helpers;

class UserSessionException extends \Exception {}

$cwd = explode('/', getcwd());
unset($cwd[(count($cwd)-1)]);
$base = implode('/', $cwd);

require_once("{$base}/apps/user/models/User.php");

class UserSession {
	
	private $sandbox = NULL;
	
	public function __construct($sandbox) {
		$this->sandbox = &$sandbox;
	}	
	
	public function setUser($user){
		$this->sandbox->getHelper('session')->write('userSession', serialize($user));
	}
	
	public function getUser(){
		$user = $this->sandbox->getHelper('session')->read('userSession');
		return is_null($user) ? NULL : unserialize($user);
	}
	
	public function isGuest(){
		return is_null($this->sandbox->getHelper('session')->read('userSession'));
	}
	
	public function getPermissions(){
		$user = $this->getUser();
		return is_null($user) ? NULL : $user->getPermissions();
	}
		
}