<?php

namespace helpers;

class StorageException extends \Exception {}

class Storage {
	
	private $host = NULL;
	
	private $user = NULL;
	
	private $password = NULL;
	
	private $schema = NULL;
	
	private $resource = NULL;
	
	private $result = NULL;
	
	private $columns = NULL;
	
	private $table = NULL;
	
	private $parameters = NULL;

	public function __construct($settings) {
		try {
			$this->host = $settings['host'];
			$this->user = $settings['user'];
			$this->password = $settings['password'];
			$this->schema = $settings['schema'];
			$this->resource = @new \mysqli($this->host, $this->user, $this->password, $this->schema);
			if($this->resource->connect_error) {
				throw new StorageException($this->resource->connect_error);
			}
		} catch (\Exception $e) {
			throw new StorageException($this->resource->connect_error);
		}
	}

	public function query($query, $multi_query = false){
		try {
			if($multi_query){
				$this->result = $this->resource->multi_query($query);
			}else{
				$this->result = $this->resource->query($query);
			}
			if(strlen($this->resource->error)){
				throw new StorageException($this->resource->error);
			}
			if($this->result instanceof \mysqli_result) {
				return $this->fetch();
			}
			return NULL;
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage() . " : $query"));
		}
	}
	
	public function multiQuery($query){
		try {
			$this->query($query, true);
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}

	public function insert($arguments){
		try {
			if(!array_key_exists('table', $arguments)) {
				throw new StorageException('Please define a table for insert operation');
			}			
			$this->examineTable(trim($arguments['table']));
			$this->parameters = array();
			foreach ($arguments['content'] as $key => $value){
				$this->parameters[] = array($key, $value);
				$columns[] = "`{$key}`";
				$markers[] = '?';
			}
			$query = sprintf("INSERT INTO `%s` (%s) VALUES(%s)", trim($arguments['table']), implode(', ', $columns), implode(', ', $markers));
			$statement = $this->prepare($query);
			$this->bind($statement);
			if($statement->execute()){
				return $this->getInsertID();
			}
			throw new StorageException($this->resource->error);
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}
	
	public function select($arguments){
		try {
			if(strlen(trim($arguments['table'])) == 0) {
				return NULL;
			}
			$this->examineTable(trim($arguments['table']));
			$this->parameters = array();
			if(array_key_exists('fields', $arguments)){
				$columns = implode(', ', $arguments['fields']);
			}else{
				$columns = implode(', ', array_keys($this->columns));
			}
			$query[] = sprintf("SELECT %s FROM `%s`", $columns, trim($arguments['table']));
			$whereQuery = $this->whereQuery($arguments);
			if(!is_null($whereQuery)) {
				$query[] = $whereQuery;
			}
			if(array_key_exists('order', $arguments)){
				$query[] = sprintf("ORDER BY `%s` %s", $this->sanitize($arguments['order'][0]), $this->sanitize($arguments['order'][1]));
			}
			if(array_key_exists('limit', $arguments)){
				$query[] = sprintf("LIMIT %d, %d", $this->sanitize($arguments['limit'][0]), $this->sanitize($arguments['limit'][1]));
			}
			$statement = $this->prepare((implode(' ', $query)));
			$this->bind($statement);
			if($statement->execute()){
				$statement->store_result();
				$meta = $statement->result_metadata();
				while ($column = $meta->fetch_field()) {
					$parameters[] = &$result[($column->name)];
				}
				call_user_func_array(array($statement, 'bind_result'), $parameters);
				while($statement->fetch()){
					$row = array();
					foreach($result as $key => $value){
						$row[$key] = $value;
					}
					$rows[] = $row;
				}
				$statement->free_result();
				return isset($rows) ? $rows : NULL;
			}
			throw new StorageException($this->resource->error);
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}
	
	public function update($arguments){
		try {
			if(!array_key_exists('constraints', $arguments)) {
				throw new StorageException('You must provide constraints for update queries.');
			}
			$this->examineTable(trim($arguments['table']));
			$this->parameters = array();
			$query[] = sprintf("UPDATE `%s` SET", trim($arguments['table']));
			foreach ($arguments['content'] as $key => $value){
				$this->parameters[] = array($key, $value);
				$content[] = sprintf("`%s` = ?", $key);
			}
			$query[] = implode(', ', $content);
			$whereQuery = $this->whereQuery($arguments);
			if(!is_null($whereQuery)) {
				$query[] = $whereQuery;
			}
			$statement = $this->prepare((implode(' ', $query)));
			$this->bind($statement);
			if($statement->execute()){
				return $this->resource->affected_rows;
			}
			throw new StorageException($this->resource->error);
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}
	
	public function delete($arguments){
		try {
			if(!array_key_exists('constraints', $arguments)) {
				throw new StorageException('You must provide constraints for delete queries.');
			}
			$this->examineTable(trim($arguments['table']));
			$this->parameters = array();
			$query[] = sprintf("DELETE FROM `%s`", trim($arguments['table']));
			$whereQuery = $this->whereQuery($arguments);
			if(!is_null($whereQuery)) {
				$query[] = $whereQuery;
			}
			$statement = $this->prepare((implode(' ', $query)));
			$this->bind($statement, $this->parameters);
			if($statement->execute()){
				return $this->resource->affected_rows;
			}
			throw new StorageException($this->resource->error);
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}
	
	public function getInsertID(){
		return $this->resource->insert_id;
	}
	
	public function sanitize($value){
		return mysqli_real_escape_string($this->resource, trim($value));
	}
	
	private function whereQuery($arguments){
		if(!array_key_exists('constraints', $arguments)) return NULL;
		$glue = (array_key_exists('operator', $arguments)) ? ($arguments['operator']) : ('AND');
		foreach($arguments['constraints'] as $key => $value){
			if(is_null($value)){
				$constraints[] = sprintf("`%s` IS NULL", $key);
				continue;
			}
			$this->parameters[] = array($key, $value);
			$constraints[] = sprintf("`%s` = ?", $key);
		}
		return 'WHERE ' . implode(" {$glue} ", $constraints);
	}
	
	private function fetch(){
		try {
			if($this->result->num_rows === 0) return NULL;
			while($row = $this->result->fetch_assoc()){
				$rows[] = $row;
			}
			return $rows;
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}
	
	private function prepare($query){
		$statement = $this->resource->prepare($query);
		if($statement) {
			return $statement;
		}
		throw new StorageException($this->resource->error);
	}
		
	private function bind(&$statement){
		if(count($this->parameters) === 0) return;
		if(call_user_func_array(array($statement, "bind_param"), $this->createBindParameters())) return;
		throw new StorageException($this->resource->error);
	}
	
	private function createBindParameters(){
		foreach($this->parameters as $parameter){
			$values[] = &$parameter[1];
			$types[] = $this->columns[$parameter[0]]['type'];
		}
		$typeCharacters = (array) implode('', $types);
		return array_merge($typeCharacters , $values);
	}
	
	private function examineTable($table){
		try {
			$this->columns = NULL;
			$columns = $this->query(sprintf("SHOW COLUMNS FROM `%s`", $table));
			foreach($columns as $column){
				$this->columns[($column['Field'])]['type'] = $this->typeCharacter($column['Type']);
			}
		} catch (\Exception $e) {
			throw new StorageException(($e->getMessage()));
		}
	}

	private function typeCharacter($type){
		$matches = preg_split("/[\(\)]+/", "$type");
		switch(strtoupper($matches[0])){
			case 'INT':
			case 'BIGINT':
			case 'TINYINT':
			case 'SMALLINT':
			case 'MEDIUMINT':
			case 'INTEGER':
			case 'YEAR':
				return 'i';
				break;
			case 'FLOAT':
			case 'REAL':
			case 'DECIMAL':
			case 'DOUBLE':
				return 'd';
				break;
			case 'TINYTEXT':
			case 'MEDIUMTEXT':
			case 'LONGTEXT':
			case 'DATE':
			case 'DATETIME':
			case 'TIMESTAMP':
			case 'TIME':
			case 'TEXT':
			case 'VARCHAR':
			case 'CHAR':
			case 'ENUM':
			case 'SET':
				return 's';
			case 'LONGBLOB':
			case 'MEDIUMBLOB':
			case 'BLOB':
			case 'TINYBLOB':
				return 'b';
				break;
		}
	}
	
}