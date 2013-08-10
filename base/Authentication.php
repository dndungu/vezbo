<?php

namespace base;

use apps\user\User;

class AuthenticationException extends \Exception {};

class Authentication {
	
	protected $sandbox = NULL;
	
	protected $portlets = NULL;
		
	protected $user = NULL;
		
	protected $sitemap = NULL;
	
	public function __construct(&$sandbox) {
		$this->sandbox = &$sandbox;
		$this->user = $this->sandbox->getHelper('userSession')->getUser();
		$this->sandbox->listen('aliasing.passed', 'init', $this);
	}
	
	public function init($data){
		try{
			$portal = new \SimpleXMLElement($this->sandbox->getMeta('portal')->asXML());
			$this->shieldPortal($portal);
			$this->shieldPortlets($portal);
			$this->generateNavigation();
			return $this->sandbox->fire('authentication.passed', $portal);
		}catch (AuthenticationException $e){
			$this->sandbox->logException($e);
			return $this->sandbox->fire('authentication.failed');
		}
	}
	
	private function shieldPortal(){
		if(!$this->attestPermissions($this->sandbox->getMeta('portal')->access->permission)){
			throw new AuthenticationException('Access to portal denied.');
		}
	}
	
	private function shieldPortlets(){
		foreach($this->sandbox->getMeta('portal')->portlet as $portlet){
			if($this->attestPermissions($portlet->access->permission)) {
				$this->portlets[] = $portlet->asXML();
			}
		}
		if(is_null($this->portlets)){
			throw new AuthenticationException('Access to any portlets denied.');
		}
	}
	
	protected function generateNavigation(){
		$package = $this->sandbox->getMeta('package');
		$sitemap = NULL;
		foreach ($package as $portal){
			if(!$this->attestPermissions($portal->access->permission)) continue;
			foreach($portal->navigation as $match){
				$uri['id'] = (string) $match->attributes()->id;
				$uri['uri'] = (string) $match->attributes()->uri;
				$uri['parent'] = (string) $match->attributes()->parent;
				$uri['group'] = (string) $match->attributes()->group;
				$uri['label'] = $this->sandbox->getHelper('translation')->translate((string) $match->attributes()->label);
				$uri['weight'] = (int) $match->attributes()->weight;
				$uri['class'] = (string) $match->attributes()->class;
				$sitemap[] = $uri;
			}
		}
		$this->sandbox->setMeta('navigation', $sitemap);
	}
		
	public function attestPermissions($arguments){
		$permissions = (array) $arguments;
		$userPermissions = is_null($this->user) ? array() : (array) $this->user->getPermissions();
		foreach($permissions as $permission){
			if((string) $permission == "public.permission") {
				return true;
			}
			if(in_array((string) $permission, $userPermissions)){
				return true;
			}
		}
		return false;
	}
		
}

?>