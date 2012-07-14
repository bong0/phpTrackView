<?php

/*
	This file creates a new MySQL connection using the PDO class.
	The login details are taken from config.php.
*/

class Database extends PDO {
	function __construct() {
        @parent::__construct(
			sprintf('mysql:host=%1$s;dbname=%2$s;charset=UTF-8', DB_HOST, DB_NAME),
			DB_USER,
			DB_PASS
		);
		$this->query('SET NAMES ´utf8´');
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	function insert($table, $fields, $data) {
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
