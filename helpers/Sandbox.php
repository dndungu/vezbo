<?php

namespace helpers;

class SandboxException extends \Exception {}

class Sandbox {
	
	private $meta = array();
				
	private $events = array();
	
	private $helpers = array();
	
	private $globalStorage = NULL;
		
	private $localStorage = NULL;
		
	public function __construct(&$storage) {
		$this->globalStorage = $storage;
		$this->init();
	}
	
	private function init(){
		try {
			$this->setBase();
			$this->setURI();
			$this->setMethod();
			$this->initHelper('Input');
			$this->initHelper('Session');
			$this->initHelper('UserSession');
			$this->initHelper('Site');
			$this->initHelper('Utility');
		} catch (\Exception $e) {
			throw new SandboxException($e->getMessage());
		}
	}
	
	public function listen($types = NULL, $method = NULL, $instance = NULL){
		if(is_string($types)) {
			$types = array($types);
		}
		foreach($types as $type){
			$this->events[$type][] = array('instance' => $instance, 'method' => $method);
		}
		return NULL;
	}
	
	public function fire($type = NULL, &$data = NULL){
		if(is_null($type) || !array_key_exists($type, $this->events)) return;
		$listeners = $this->events[$type];
		$parameter = is_array($data) ? ($data) : array(&$data);
		$results = NULL;
		foreach($listeners as $listener){
			$callback = is_null($listener['instance']) ? $listener['method'] : array($listener['instance'], $listener['method']);
			$results[] = call_user_func_array($callback, array(&$data));
		}
		return $results;
	}
	
	public function getHelper($helper){
		return $this->helpers[strtolower($helper)];
	}
	
	public function getGlobalStorage(){
		return $this->globalStorage;
	}
		
	public function setLocalStorage(&$storage){
		$this->localStorage = &$storage;
	}
	
	public function getLocalStorage(){
		return $this->localStorage;
	}
						
	private function setBase(){
		$cwd = explode('/', getcwd());
		unset($cwd[(count($cwd)-1)]);		
		$this->meta['base'] = implode('/', $cwd);
	}
		
	private function setURI(){
		$resource = rtrim($_SERVER['REQUEST_URI'], "/");
		$this->meta['URI'] = strlen($resource) == 0 ? "/" : $resource;
	}
		
	private function setMethod(){
		$this->meta['method'] = $_SERVER['REQUEST_METHOD'] == "GET" ? "doGet" : "doPost";
	}
		
	public function setMeta($key, &$value){
		$this->meta[$key] = $value;
	}
	
	public function getMeta($key){
		return array_key_exists($key, $this->meta) ? $this->meta[$key] : NULL;
	}
	
	public function initHelper($helper){
		$key = strtolower($helper);
		if(array_key_exists($key, $this->helpers)) {
			return $this->helpers[$key];
		} else {
			try {
				$class = "\helpers\\$helper";
				$base = $this->getMeta('base');
				require_once("$base/helpers/$helper.php");
				$this->helpers[$key] = new $class($this);
				return $this->helpers[$key];
			}catch(HelperException $e){
				$this->fire('sandbox.error', ($e->getMessage()));
			}
		}
	}
	
	public function logAccess($latency){
		$insert = array('table' => 'access');
		$insert['content'] = $this->logMeta();
		$insert['content']['type'] = (string) $this->getMeta('portal')->attributes()->type;
		$insert['content']['latency'] = $latency;
		$insert['content']['siteID'] = $this->getHelper('site')->getID();
		$insert['content']['hash'] = $this->getHelper('session')->getHash();
		try{
			$this->getGlobalStorage()->insert($insert);
		}catch(\helpers\StorageException $e){
			error_log($e->getMessage());
		}
	}
	
	public function logException($e){
		$log = $this->logMeta();
		$log['line'] = $e->getLine();
		$log['source'] = $e->getFile();
		$log['message'] = $e->getMessage();
		$insert = array('table' => 'error', 'content' => $log);
		try{
			$this->getGlobalStorage()->insert($insert);
		}catch(\helpers\StorageException $e){
			error_log($e->getMessage());
		}
	}
	
	private function logMeta() {
		$user = $this->getHelper('userSession')->getUser();
		$log['userID'] = is_null($user) ? NULL : $user->getUserID();
		$log['resource'] = $this->getMeta('URI');
		$log['IP'] = $_SERVER['REMOTE_ADDR'];
		$log['creationTime'] = microtime(true);
		return $log;
	}
		
}

?>