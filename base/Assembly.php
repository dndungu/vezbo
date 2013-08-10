<?php

namespace base;

class Assembly {
	
	protected $sandbox = NULL;
	
	protected $response = NULL;
	
	public function __construct(&$sandbox) {
		$this->sandbox = &$sandbox;
		$this->sandbox->listen('routing.passed', 'init', $this);
	}
	
	public function init($data) {
		$this->response = $data;
		try {
			$type = (string) $this->sandbox->getMeta('portal')->attributes()->type;
			switch($type){
				case "raw":
					$content = $this->toString();
					break;
				case "json":
					$content = $this->toJSON();
					break;
				case "xml":
					$content = $this->toXML();
					break;
				case "html":
					$this->response['base']['locale'] = $this->sandbox->getHelper('translation')->getLocale();
					$content = $this->toHTML();
					break;
			}
			return $this->sandbox->fire('assembly.passed', $content);
		}catch(BaseException $e){
			$this->sandbox->logException($e);
			return $this->sandbox->fire('assembly.failed');
		}
	}
	
	protected function toString(){
		$string = array();
		foreach($this->response as $app){
			foreach($app as $controller => $content){
				$string[] = $content;
			}
		}
		return implode("\n", $content);
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
		
	protected function toJSON(){
		return json_encode($this->utf8Encode($this->response), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
	}
	
	protected function toXML($response = null, &$xml = null){
		try {
			if(is_null($xml)) {
				$xml = new \SimpleXMLElement("<?xml version='1.0'?><response></response>");
			}
			if(is_null($response)) {
				$response = $this->utf8Encode($this->response);
			}
			if(is_array($response) || is_object($response)){
				$this->buildXML($response, $xml);
			}
			return $xml->asXML();
		} catch (\Exception $e) {
			throw new BaseException($e->getMessage());
		}
	}
	
	protected function buildXML(&$content, &$xml){
		foreach($content as $key => $value){
			$node = is_numeric($key) ? "node-$key" : $key;
			if(is_array($value) || is_object($value)){
				$child = $xml->addChild($node);
				$this->toXML($value, $child);
			}else{
				$xml->addChild($node, htmlentities((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8'));
			}
		}
	}
	
	protected function toHTML(){
		try {
			$settings = $this->sandbox->getMeta('settings');
			$base = $this->sandbox->getMeta('base');
			$theme = $settings['theme'];
			$xslt = new \XsltProcessor();
			$xslt->registerPHPFunctions(array('date'));
			$site = $this->sandbox->getMeta('site');
			$home = $site['home'];
			$template = $this->sandbox->getMeta('template');
			if(is_null($template)){
				$template = "{$base}/templates/{$theme}/" . $this->sandbox->getMeta('portal')->attributes()->template;
			}else{
				$template = "{$base}/templates/{$template}";
			}
			$xslt->importStylesheet(simplexml_load_file($template));
			$html = $xslt->transformToXML(simplexml_load_string($this->toXML()));
			return $html;
		} catch (\Exception $e) {
			throw new BaseException($e->getTraceAsString());
		} 
	}
	
}
?>