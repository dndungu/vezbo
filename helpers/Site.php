<?php

namespace helpers;

class SiteException extends \Exception {}

class Site {
	
	private $sandbox = NULL;
	
	private $ID = NULL;
		
	private $home = NULL;
	
	private $alias = NULL;
	
	private $settings = NULL;
	
	public function __construct(&$sandbox) {
		$this->sandbox = &$sandbox;
		$this->init();
	}
	
	public function init(){
		try {
			$this->setAlias(strtolower($_SERVER['HTTP_HOST']));
			$site = $this->findSite();
			$this->setID($site['siteID']);
			$this->sandbox->getHelper('session')->write('site', $site['siteID']);
			$this->setHome($site['home']);
			$settings = $this->findSettings();
			$this->setSettings($settings);
			$this->initTranslation();
			$this->initLocalStorage();
		} catch (\Exception $e) {
			throw new SiteException($e->getMessage());
		}
	}
	
	protected function initTranslation(){
		try {
			$this->sandbox->initHelper('Translation');
		}catch(\helpers\HelperException $e){
			throw new \base\BaseException($e->getMessage());
		}
	}
	
	protected function initLocalStorage(){
		$settings = $this->sandbox->getMeta('settings');
		$storage = new \helpers\Storage($settings);
		$this->sandbox->setLocalStorage($storage);
	}
		
	private function findSite(){
		try {
			$query = sprintf("SELECT `siteID`, `home`, `source`, `name` FROM `alias` LEFT JOIN `site` USING (`siteID`) WHERE `alias`.`name` = '%s' LIMIT 1", $this->getAlias());
			$sites = $this->sandbox->getGlobalStorage()->query($query);
			if(!is_null($sites)) {
				$this->sandbox->setMeta('site', $sites[0]);
				return $sites[0];
			}
			header("Location: http://zatiti.com");
			exit;
		}catch(\helpers\StorageException $e){
			$this->sandbox->logException($e);
			throw new SiteException($e->getMessage());
		}
	}
	
	private function findSettings(){
		$query = sprintf("SELECT * FROM `setting` WHERE `siteID` = %d", $this->getID());
		$rows = $this->sandbox->getGlobalStorage()->query($query);
		if(is_null($rows)){
			return NULL;
		}else{
			$settings = array();
			foreach($rows as $row){
				$settings[$row['name']] = $row['content'];
			}
			$this->sandbox->setMeta('settings', $settings);
			return $settings;
		}
	}
	
	public function getID(){
		return $this->ID;
	}
	
	public function setID($ID){
		$this->ID = $ID;
	}
	
	public function getUser(){
		return $this->user;
	}
	
	public function setUser($user){
		$this->user = $user;
	}
		
	public function getHome(){
		return $this->home;
	}
	
	public function setHome($home){
		$this->home = $home;
	}
	
	public function getAlias(){
		return $this->alias;
	}
	
	public function setAlias($alias){
		$this->alias = $alias;
	}
	
	public function getSettings(){
		return $this->settings;
	}
	
	public function setSettings($settings){
		$this->settings = $settings;
	}
		
}