<?php

namespace helpers;

class User {
	
	protected $userID = NULL;
	
	protected $guest = NULL;
	
	protected $IP = NULL;
	
	protected $login = NULL;
	
	protected $firstname = NULL;
	
	protected $middlename = NULL;
	
	protected $lastname = NULL;
	
	protected $email = NULL;
		
	protected $password = NULL;
	
	protected $creationTime = NULL;
		
	protected $permissions = NULL;
	
	protected $sandbox = NULL;
	
	public function __construct(&$sandbox) {
		$this->sandbox = &$sandbox;
		$user = $this->sandbox->getHelper('session')->read('user');
		if(is_null($user)){
			$user = $this->createGuest();
		} else {
			$this->isGuest('No');
			$this->setUser($user);
		}
		$this->sandbox->getHelper('session')->write('user', $this->getUser());
	}
	
	protected function createGuest() {
		$this->setIP($_SERVER['REMOTE_ADDR']);
		$this->setCreationTime(time());
		$this->setRole('guest');
		$this->isGuest('Yes');
		$this->setPermissions($this->getGuestPermissions());
		$insert = array('table' => 'guest', 'content' => array('IP' => $this->getIP(), 'creationTime' => $this->getCreationTime()));
		$guestID = $this->getStorage()->insert($insert);
		$this->setLogin('guest-'.$guestID);
		$this->setID($guestID);
		return $this->getUser();
	}
	
	protected function getGuestPermissions(){
		$permissions = $this->getStorage()->query("SELECT `title` FROM `rolePermission` LEFT JOIN `permission` USING (`permissionID`) WHERE `roleID` IN (SELECT `roleID` FROM `role` WHERE `title` = 'guest')");
		$this->permissions = array();
		if($permissions){
			foreach($permissions as $permission){
				$this->permissions[] = $permission['title']; 
			}
		}
		return $this->permissions;
	}
		
	public function setID($ID) {
		$this->userID = $ID;
	}

	public function getID() {
		return $this->userID;
	}
	
	public function isGuest($guest = NULL) {
		if(is_null($guest)){
			return $this->guest;
		} else {
			$this->guest = $guest;
		}
	}
	
	public function setIP($IP){
		$this->IP = $IP;
	}
	
	public function getIP(){
		return $this->IP;
	}
	
	public function setLogin($login) {
		$this->login = $login;
	}
	
	public function getLogin() {
		return $this->login;
	}
	
	public function setFirstname($firstname){
		$this->firstname = $firstname;
	}
	
	public function getFirstname(){
		return $this->firstname;
	}
	
	public function setMiddlename($middlename){
		$this->middlename = $middlename;
	}
	
	public function getMiddlename(){
		return $this->middlename;
	}	
	
	public function setLastname($lastname){
		$this->lastname = $lastname;
	}
	
	public function getLastname(){
		return $this->lastname;
	}	
	
	public function setEmail($email) {
		$this->email = $email;
	}
	
	public function getEmail() {
		return $this->email;
	}

	public function setPassword($password) {
		$this->password = $password;
	}
	
	public function getPassword() {
		return $this->password;
	}
		
	public function setCreationTime($creationTime) {
		$this->creationTime = $creationTime;
	}

	public function getCreationTime() {
		return $this->creationTime;
	}
		
	public function getRoles(){
		return $this->roles;
	}
	
	public function setUser($user){
		$this->setID($user['ID']);
		$this->setEmail($user['email']);
		$this->setPassword(array_key_exists("password", $user) ? $user['password'] : NULL);
		$login = array_key_exists('login', $user) ? $user['login'] : $user['email'];
		$this->setLogin($login);
		$this->setFirstname($user['firstname']);
		$this->setMiddlename($user['middlename']);
		$this->setLastname($user['lastname']);
		$this->setPermissions($user['permissions']);
		$this->setCreationTime($user['creationTime']);
		$this->isGuest($user['isGuest']);
	}
	
	public function getUser(){
		return array('ID' => $this->getID(),
					 'login' => $this->getLogin(),
					 'firstname' => $this->getFirstname(),
					 'middlename' => $this->getMiddlename(),				
					 'lastname' => $this->getLastname(),				
					 'email' => $this->getEmail(),
					 'password' => $this->getPassword(),
					 'permissions' => $this->getPermissions(),
				 	 'creationTime' => $this->getCreationTime(),
					 'isGuest' => $this->isGuest());
	}
	
	public function signUp(){
		try {
			$user = $this->validateSignUp();
			$user['login'] = $user['login'];
			$user['creationTime'] = time();
			$user['site'] = $this->sandbox->getHelper('site')->getID();
			$insert['table'] = 'user';
			$insert['content'] = $user;
			$this->getStorage()->insert($insert);
			$user['isGuest'] = 'No';
			$user['roles'] = NULL;
			$user['ID'] = $this->getStorage()->getInsertID();
			$this->setUser($user);
			$this->sandbox->getHelper('session')->write('user', $this->getUser());
		} catch(HelperException $e){
			throw new HelperException($e->getMessage());
		}
	}
	
	protected function validateSignUp(){
		
		$input = $this->sandbox->getHelper('input');
		$translator = $this->sandbox->getHelper('translation');
		
		$user['email'] = $input->postEmail('email');
		if(!$user['email']) throw new HelperException($translator->translate('invalid.email'));
		
		$user['login'] = $input->postString('login');
		if(!$user['login']) throw new HelperException($translator->translate('invalid.login'));
		
		if($this->userExists($user['email'])) throw new HelperException($user['email'].$translator->translate('email.inuse'));
		$user['password'] = $input->postPassword('password');
		if(!$user['password']) throw new HelperException($translator->translate('invalid.password'));
		
		$passwordconfirm = $input->postPassword('passwordconfirm');
		if(!$passwordconfirm) throw new HelperException($translator->translate('mismatched.passwords'));
		if($user['password'] != $passwordconfirm) throw new HelperException($translator->translate('mismatched.passwords'));
		
		return $user;
	}
	
	public function userExists($login){
		$site = $this->sandbox->getHelper('site')->getID();
		$query = sprintf("SELECT COUNT(*) AS n FROM `user` WHERE `siteID` = %d AND `email` = '%s'", $site, $login);
		$rows = $this->getStorage()->query($query);
		return $rows[0]['n'] > 0 ? true : false;
	}
	
	public function signIn(){
		try {
			$site = $this->sandbox->getHelper('site')->getID();
			$user = $this->authenticateUser();
			if(is_null($user)) throw new HelperException($this->sandbox->getHelper('translation')->translate('incorrect.password'));
			$this->ownGuest($user['ID']);
			$permissionQuery = sprintf("SELECT `title` FROM `rolePermission` LEFT JOIN `permission` USING (`permissionID`) WHERE `role` IN (SELECT `role` FROM `userRole` WHERE `user` = %d AND `siteID` = %d)", $user['ID'], $site);
			$permissions = $this->getStorage()->query($permissionQuery);
			$this->permissions = array('user.permission');
			if($permissions){
				foreach($permissions as $row){
					$this->permissions[] = $row['title'];
				}
			}
			$roles = $this->getStorage()->query(sprintf("SELECT `title` FROM `userRole` LEFT JOIN `role` USING (`roleID`) WHERE `user` = %d AND `userRole`.`siteID` = %d", $user['ID'], $site));
			$this->roles = array();
			if($roles){
				foreach($roles as $row){
					$this->roles[] = $row['title'];
				}
			}
			$user['isGuest'] = 'No';
			$user['roles'] = $this->roles;
			$user['permissions'] = $this->permissions;
			$this->setUser($user);
			$this->sandbox->getHelper('session')->write('user', $this->getUser());
		}catch (HelperException $e) {
			throw new HelperException($e->getMessage());
		}
	}
	
	protected function authenticateUser(){
		$input = $this->validateSignIn();
		$site = $this->sandbox->getHelper('site')->getID();
		$query = sprintf("SELECT * FROM `user` WHERE `siteID` = %d AND `email` = '%s'", $site, $input['email']);
		$users = $this->getStorage()->query($query);
		if(is_null($users)) throw new HelperException($this->sandbox->getHelper('translation')->translate('incorrect.login'));
		foreach($users as $user){
			if($user['password'] != $input['password']) continue;
			return $user;
		}
		return NULL;
	}
		
	protected function validateSignIn(){
		$input = $this->sandbox->getHelper('input');
		$translator = $this->sandbox->getHelper('translation');
		
		$user['email'] = $input->postEmail('email');
		if(!$user['email']) throw new HelperException($translator->translate('invalid.login'));
				
		$user['password'] = $input->postPassword('password');
		if(!$user['password']) throw new HelperException($translator->translate('invalid.password'));

		$user['password'] = md5($user['password']);
		
		return $user;
		
	}
	
	protected function ownGuest($ID){
		if($this->isGuest() === 'No') return;
		$update['table'] = 'guest';
		$update['content'] = array('user' => $ID);
		$update['constraints'] = array('ID' => $this->getID());
		$this->getStorage()->update($update);
		$this->isGuest('No');
	}	
	
	public function changePassword(){
		$translator = $this->sandbox->getHelper('translation');
		try {
			if($this->isGuest()) {
				throw new HelperException($translator->translate('login.required'));
			}
			$password = $this->validateChangePassword();
			$update['table'] = 'user';
			$update['content'] = array('password' => $password);
			$update['constraints'] = array('ID' => $this->getID());
			$this->getStorage()->update($update);
			$this->sandbox->getHelper('session')->purge();
		}catch(HelperException $e){
			throw new HelperException($e->getMessage());
		}
	}
	
	protected function validateChangePassword(){
		$translator = $this->sandbox->getHelper('translation');
		$input = $this->sandbox->getHelper('input');
	
		$currentpassword = $input->postPassword('passwordcurrent');
		if(md5($currentpassword) != $this->getPassword()) throw new HelperException($translator->translate('incorrect.password'));
		
		$password = $input->postPassword('password');
		if(!$password) throw new HelperException($translator->translate('invalid.password'));
		
		$passwordconfirm = $input->postPassword('passwordconfirm');
		if($password != $passwordconfirm) throw new HelperException($translator->translate('mismatched.passwords'));
		
		return md5($password);		
	}
		
	public function signOut(){
		$this->sandbox->getHelper('session')->purge();
	}

	public function resetPassword(){
		$translator = $this->sandbox->getHelper('translation');
		
		$token = $this->randomString(32);
		$hash = md5($token);
		
		$login = $this->sandbox->getHelper('input')->postEmail('email');
		if(!$login) throw new HelperException($translator->translate('invalid.login'));
		
		$site = $this->sandbox->getHelper('site')->getID();
		$loginQuery = sprintf("SELECT `userID`, `firstname` FROM `user` WHERE `email` = '%s'", $this->getStorage()->sanitize($login));
		$users = $this->getStorage()->query($loginQuery);
		if(is_null($users)) throw new HelperException($translator->translate('incorrect.login'));
		$user = $users[0];
		
		$message = $translator->translate('resetpassword.mail');
		$message = nl2br($message, true);
		$firstname = is_null($user['firstname']) ? $translator->translate('salutation') : $user['firstname'];
		$alias = strtolower($_SERVER['HTTP_HOST']);
		$message = str_replace('{{firstname}}', $firstname, $message);
		$n = 2;
		$message = str_replace('{{login}}', $login, $message, $n);
		$message = str_replace('{{alias}}', $alias, $message);
		$message = str_replace('{{token}}', $token, $message);
		$subject = $translator->translate('resetpassword');
		try {
			$emailer = $this->sandbox->initHelper('Emailer');
			if($emailer->send($login, $subject, $message)) {
				$insert['table'] = 'recover';
				$insert['content']['user'] = $user['ID'];
				$insert['content']['hash'] = $hash;
				$insert['content']['creationTime'] = time();
				$recoverID = $this->getStorage()->insert($insert);
				return $recoverID;
			}else{
				throw new HelperException($translator->translate('emailer.error'));
			}
		}catch(\helpers\HelperException $e){
			throw new HelperException($e->getMessage());
		}
	}
	
	public function recoverPassword(){
		$storage = $this->getStorage();
		$translator = $this->sandbox->getHelper('translation');
		$input = $this->sandbox->getHelper('input');
		
		$login = $input->postEmail('login');
		if(!$login || !$this->userExists($login)) throw new HelperException($translator->translate('invalid.login'));
				
		$token = trim($input->postString('token'));
		if(strlen($token) < 32) throw new HelperException($translator->translate('invalid.token'));
		$expiry = time()-(3600);
		$tokenQuery = sprintf("SELECT `userID` FROM `recover` WHERE `creationTime` > %d AND `hash` = '%s' AND `userID` IN (SELECT `userID` FROM `user` WHERE `login` = '%s')", $expiry, $storage->sanitize(md5($token)), $storage->sanitize($login));
		$users = $storage->query($tokenQuery);
		if(is_null($users)) throw new HelperException($translator->translate('invalid.token'));
		
		$password = $input->postPassword('password');
		if(!$password) throw new HelperException($translator->translate('invalid.password'));
		$passwordconfirm = $input->postPassword('passwordconfirm');
		if($password != $passwordconfirm) throw new HelperException($translator->translate('mismatched.passwords'));
		$this->setID($users[0]['user']);
		
		$update['table'] = 'user';
		$update['content'] = array('password' => $password);
		$update['constraints'] = array('ID' => $this->getID());
		$this->getStorage()->update($update);
	}
	
	private function getStorage(){
		return $this->sandbox->getGlobalStorage();
	}
	
	public function randomString($size = 8){
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz023456789";
		$i = 0;
		$s = array();
		while ($i++ <= $size) {
			$s[] = substr($chars, (rand() % 59), 1);
		}
		return join("", $s);		
	}
	
	public function setPermissions($permissions){
		$this->permissions = $permissions;
	}
	
	public function getPermissions(){
		return $this->permissions;
	}
	
}
?>