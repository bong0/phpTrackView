<?php

class Track extends Model {

	public		$id;
	public		$title;
	public		$filename;
	public		$created;
	public		$modified;
	protected	$deleted = 0;

	public function insert() {
		global $db;

		if ($this->id) throw new Exception('Can\'t insert record with id!');

		$db->insert(
			DB_TABLE_TRACKS,
			array(
				'id'		=> ':id',
				'title'		=> ':title',
				'filename'	=> ':filename',
				'created'	=> 'NOW()',
				'modified'	=> 'NOW()',
				'deleted'	=> ':deleted',
			),
			$this
		);
		
	}
	
	// returns an array with Track objects
	public static function find($arr = NULL) {
		global $db;
		$conditions = array();
		if (!empty($arr)) {
			if (isset($arr['id']))		$conditions[] = 'id=:id';
			if (isset($arr['title']))	$conditions[] = 'title=:title';
		}
//		throw new Exception('Unsupported property!');
		$where = '';
		if (count($conditions)) $where = ' WHERE ' . join(' AND ', $conditions);
		$st = $db->prepare('SELECT * FROM ' . DB_TABLE_TRACKS . $where);
		
		$st->execute($arr);
		
		return $st->fetchAll(PDO::FETCH_CLASS, 'Track');
	}
}

?>
