<?php

namespace base;

class Controller {
	
	protected $sandbox = NULL;
	
	protected $handlers = NULL;
		
	public function __construct(&$sandbox){
		$this->sandbox = &$sandbox;
		$this->initHandlers();
		$this->initListeners();
		$this->sandbox->fire('request.passed');
	}
	
	protected function initListeners(){
		$this->sandbox->listen('aliasing.failed', 'sendHeader404', $this);
		$this->sandbox->listen('authentication.failed', 'sendDisallowed', $this);
		$this->sandbox->listen('routing.failed', 'sendHeader503', $this);
		$this->sandbox->listen('assembly.failed', 'sendHeader503', $this);
		$this->sandbox->listen('assembly.passed', 'sendContent', $this);
	}
	
	protected function initHandlers(){
		$this->initHandler('Aliasing');
		$this->initHandler('Authentication');
		$this->initHandler('Routing');
		$this->initHandler('Assembly');
	}
	
	protected function initHandler($handler){
		try {
			$base = $this->sandbox->getMeta('base');
			$class = "\base\\$handler";
			require_once("$base/base/$handler.php");
			$this->handlers[strtolower($handler)] = new $class($this->sandbox);
		}catch(\base\Exception $e){
			$this->sandbox->logException($e);
		}
		
	}	
	
	public function sendDisallowed(){
		$type = (string) $this->sandbox->getMeta('portal')->attributes()->type;
		if($type == "html"){
			$destination = $this->sandbox->getMeta('URI');
			$this->sandbox->getHelper('session')->write('destination', $destination);
			$authenticator = (string) $this->sandbox->getMeta('navigation')->attributes()->authenticator;
			$authenticator = strlen($authenticator) ? $authenticator : '/signin';
			header("Location: {$authenticator}");
		} else {
			Response::sendHeader(403);
		}
	}
			
	public function sendContent(&$content){
		$type = (string) $this->sandbox->getMeta('portal')->attributes()->type;
		$ContentType = ($type == 'html') ? ('text/html') : ($type == 'xml' ? 'text/xml' : 'application/json');
		header("Content-Type: {$ContentType}; charset=utf-8");
		header("ETag: " . $this->sandbox->getMeta('ETag'));
		$search = array('/\>[^\S]+/s', '/[^\S]+\</s', '/(\s)+/s');
		$replace = array('>', '<', '\\1');
		print ($type == 'html') ? preg_replace($search, $replace, $content) : $content;
	}
		
	public function sendHeader404(){
		return Response::sendHeader(404);
	}

	public function sendHeader500(){
		return Response::sendHeader(500);
	}
	
	public function sendHeader503(){
		return Response::sendHeader(503);
	}	
			
}

?>