<?php

/*
	This file creates a new MySQL connection using the PDO class.
	The login details are taken from config.php.
*/

class Database extends PDO {
	function __construct() {
		switch (DB_TYPE) {
			case 'mysql':
				@parent::__construct(
					sprintf('mysql:host=%1$s;dbname=%2$s;charset=UTF-8', DB_HOST, DB_NAME),
					DB_USER,
					DB_PASS
				);
				$this->query('SET NAMES ´utf8´');
				break;
			case 'sqlite':
				@parent::__construct(
					sprintf('sqlite:%1$s', DB_NAME)
				);
				break;
			default:	throw new Exception(sprintf('Unknown database type %s', DB_TYPE));
		}
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	function insert($table, $fields, $data) {
		if(DB_TYPE === 'sqlite'){
			array_walk($fields, function(&$value, $key){
				if($value === 'NOW()'){
					$value = 'datetime()';
				}
			});
		}
		$sql = 'INSERT INTO ' . DB_TABLE_TRACKS . ' (' . join(', ', array_keys($fields)) . ')'
				. ' VALUES (' . join(', ', $fields) . ')';

		if (is_object($data) && method_exists($data, 'getObjectVars')) $data = $data->getObjectVars();

		$values = array();
		foreach ($fields as $field => $value) {
			if ($value == ":$field") $values[$field] = $data[$field];
		}

		$st = $this->prepare($sql);
		$st->execute($values);
	}

}

try {
	$db = new Database();
}
catch(PDOException $e) {
	error_log($e->getMessage());
	throw new Exception('Could not connect to database.');
}
