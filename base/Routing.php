<?php

namespace base;

class Routing {

	protected $sandbox = NULL;

	public function __construct(&$sandbox) {
		$this->sandbox = &$sandbox;
		$this->sandbox->listen('authentication.passed', 'init', $this);
	}

	public function init($portal) {
		try {
			$base = $this->sandbox->getMeta("base");
			require_once("$base/apps/Application.php");
			$ETag = md5($this->sandbox->getMeta('portal')->navigation->uri);
			foreach($portal->portlet as $portlet){
				$module = (string) $portlet->attributes()->module;
				$controller = (string) $portlet->attributes()->controller;
				$_portlet = new \SimpleXMLElement($portlet->asXML());
				$cacheContent = ($this->getCache($_portlet, $module, $controller));
				if(!$cacheContent) {
					$ETag = time();
				}
				$response[$module][$controller][] = $cacheContent ? $cacheContent : ($this->route($_portlet, $module, $controller));
			}
			$this->sandbox->setMeta('ETag', $ETag);
			$this->sandbox->fire('routing.passed', $response);
		} catch(BaseException $e) {
			$this->sandbox->logException($e);
			return $this->sandbox->fire('routing.failed');
		}
	}

	protected function getCache($portlet, $module, $controller){
		$cacheTime = (integer) $portlet->attributes()->cache;
		if($cacheTime === 0) {
			return false;
		}
		$alias = $this->sandbox->getHelper('site')->getAlias();
		$cacheBase = $this->sandbox->getMeta('base') . "/cache/{$alias}/{$module}/{$controller}";
		$URI = strtolower($this->sandbox->getMeta('URI'));
		$pathname =  str_replace('/', '-', substr($URI, 1));
		$pathname = strlen($pathname) ? $pathname : "home";
		$cacheFile = "{$cacheBase}/{$pathname}.cache";
		if(!is_file($cacheFile)){
			return false;
		}
		if(filemtime($cacheFile) < (time() - $cacheTime)){
			return false;
		}
		$content = unserialize(file_get_contents($cacheFile));		
		return $content;
	}

	protected function sourceFile($module, $controller) {
		$dir = $this->sandbox->getMeta('base')."/apps/$module";
		if(!is_dir($dir)) {
			throw new BaseException("Module '$module' does not exists");
		}
		$source = "$dir/$controller.php";
		if(!file_exists($source)){
			throw new BaseException("App controller '$source' does not exists");
		}
		if(!is_readable($source)) {
			throw new BaseException("App controller '$source' is not readable");
		}
		return $source;
	}

	protected function route($portlet, $module, $controller) {
		$source = $this->sourceFile($module, $controller);
		require_once($source);
		$appController = "apps\\$module\\$controller";
		if(!class_exists($appController)) {
			throw new BaseException("Portlet controller '{$portletClass}' class does not exist");
		}
		$instance = new $appController($this->sandbox);
		if(property_exists($portlet, 'parameter')){
			foreach($portlet->parameter as $parameter){
				$instance->setParameter((string) $parameter->attributes()->name, (string) $parameter->attributes()->value);
			}
		}
		$method = $this->sandbox->getMeta('method');
		if(!method_exists($instance, $method)) {
			throw new BaseException("Portlet controller '$method' method does not exist");
		}
		try{
			$content = call_user_func_array(array($instance, $method), array());
			$this->setCache($portlet, $module, $controller, $content);
			return $content;
		}catch(\apps\ApplicationException $e){
			throw new BaseException($e->getMessage());
		}
	}
	
	protected function utf8Encode($input){
		if(is_array($input)){
			foreach($input as $key => $value){
				$output[($this->utf8Encode($key))] = $this->utf8Encode($value);
			}
		}else if(is_string($input)){
			if(mb_detect_encoding($input) == 'UTF-8'){
				return $input;
			}else{
				return utf8_encode($input);
			}
		} else {
			return $input;
		}
		return isset($output) ? $output : NULL;
	}	

	protected function setCache($portlet, $module, $controller, $content){
		$cacheTime = (integer) $portlet->attributes()->cache;
		if($cacheTime === 0) {
			return false;
		}
		$alias = $this->sandbox->getHelper('site')->getAlias();
		$cacheBase = $this->sandbox->getMeta('base') . "/cache/{$alias}/{$module}/{$controller}";
		$URI = strtolower($this->sandbox->getMeta('URI'));
		$pathname =  str_replace('/', '-', substr($URI, 1));
		$pathname = strlen($pathname) ? $pathname : "home";
		$cacheFile = "{$cacheBase}/{$pathname}.cache";
		if(!is_dir($cacheBase)){
			mkdir($cacheBase, 0755, true);
		}
		file_put_contents($cacheFile, (serialize($this->utf8Encode($content))));
	}

}

?>