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

	public function getJSON() {
		$cache_file	= CACHE_DIR . '/' . $this->filename . '.json';
		if (is_readable($cache_file)) return file_get_contents($cache_file);

		$gpx_file	= DATA_DIR . '/' . $this->filename;
		$parser = new GpxParser();
		$parser->setInput(DATA_DIR . '/' . $this->filename);
		$parser->parse();
//		$json = json_encode($parser->getResult());

		$parsed = $parser->getResult();

		$track = &array_shift($parsed);
		while (true) {
			if (is_array(end($track))) break;
			array_pop($track);
		}
		$first = reset($track);
		$first_dist = isset($first['dist']) ? $first['dist'] : FALSE;
		$first_time = isset($first['ts'])   ? $first['ts']   : FALSE;
		$last = end($track);
		$last_dist = isset($last['dist']) ? $last['dist'] : FALSE;
		$last_time = isset($last['ts'])   ? $last['ts']   : FALSE;

		$new_data = $data = array(
			'dist' => array(
				'cad' => array(),
				'ele' => array(),
				'hr' => array(),
				'spd' => array(),
			),
			'time' => array(
				'cad' => array(),
				'dist'=> array(),
				'ele' => array(),
				'hr' => array(),
				'spd' => array(),
			)
		);
		$do_dist = ($first_dist !== FALSE && $last_dist !== FALSE);
		$do_time = ($first_time !== FALSE && $last_time !== FALSE);
		if (!$do_dist) unset($data['dist']);
		if (!$do_time) unset($data['time']);
		
		while ($row = array_shift($track)) {
			Track::setValue($data, $row, 'time', 'cad');
			Track::setValue($data, $row, 'time', 'dist');
			Track::setValue($data, $row, 'time', 'ele');
			Track::setValue($data, $row, 'time', 'hr');
			Track::setValue($data, $row, 'time', 'spd');
//			if (!isset($data['time']['cad' ][$i])) $data['time']['cad' ][$i] = array($time, NULL);
//			if (!isset($data['time']['cad' ][$i])) $data['time']['dist'][$i] = array($time, NULL);
//			if (!isset($data['time']['cad' ][$i])) $data['time']['ele' ][$i] = array($time, NULL);
//			if (!isset($data['time']['cad' ][$i])) $data['time']['hr'  ][$i] = array($time, NULL);
//			if (!isset($data['time']['cad' ][$i])) $data['time']['spd' ][$i] = array($time, NULL);
			
		}
		unset($parsed);
		unset($track);
		$new_data = array();
		Track::simplifyData($new_data, $data, 'time', 'cad');
		Track::simplifyData($new_data, $data, 'time', 'dist');
		Track::simplifyData($new_data, $data, 'time', 'ele');
		Track::simplifyData($new_data, $data, 'time', 'hr');
		Track::simplifyData($new_data, $data, 'time', 'spd');
		unset($data);
		$data = $new_data;

		/*
		$width = 4000;

		if ($do_dist) $interval_dist = ($last_dist - $first_dist) / ($width - 1);
		if ($do_time) $interval_time = ($last_time - $first_time) / ($width - 1);
		for ($i = 0; $i < $width; ++$i) {
			$time = (int)($first_time + $interval_time * $i) * 1000;
			$data['time']['cad' ][$i] = array($time, NULL);
			$data['time']['dist'][$i] = array($time, NULL);
			$data['time']['ele' ][$i] = array($time, NULL);
			$data['time']['hr'  ][$i] = array($time, NULL);
			$data['time']['spd' ][$i] = array($time, NULL);
		}

		while ($row = array_shift($track)) {
			if ($do_time && isset($row['ts'])) {
				$idx_time = round(($row['ts'] - $first_time) / $interval_time);
				$cad = isset($row['cad']) ? (float)$row['cad'] : 0;
				
				Track::fillVal($data['time']['cad'], $idx_time, $cad, $time);
				if (isset($row['dist'])) Track::fillVal($data['time']['dist'], $idx_time, $row['dist']);
				if (isset($row['ele' ])) Track::fillVal($data['time']['ele' ], $idx_time, $row['ele' ]);
				if (isset($row['hr'  ])) Track::fillVal($data['time']['hr'  ], $idx_time, $row['hr'  ]);
				if (isset($row['spd' ])) Track::fillVal($data['time']['spd' ], $idx_time, $row['spd' ]);
			}
		}
		*/
		/*
		foreach ($track as $idx => &$val) {
			if (!is_array($val)) continue;
			if (isset($val['dist'])) {
				if (!isset($first_dist)) $first_dist = $val['dist'];
				$last_dist = $val['dist'];
				if (isset($val['cad' ])) $tmp['dist']['cad' ][(float)$val['dist']] = (float)$val['cad' ];
				if (isset($val['ele' ])) $tmp['dist']['ele' ][(float)$val['dist']] = (float)$val['ele' ];
				if (!empty($val['hr' ])) $tmp['dist']['hr'  ][(float)$val['dist']] = (float)$val['hr'  ];
				if (isset($val['spd' ])) $tmp['dist']['spd' ][(float)$val['dist']] = (float)$val['spd' ];
			}
			if (isset($val['ts'])) {
				if (!isset($first_time)) $first_time = $val['ts'];
				$last_time = $val['ts'];
				if (isset($val['cad' ])) $tmp['time']['cad' ][(int)$val['ts'  ]] = (float)$val['cad' ];
				else 					 $tmp['time']['cad' ][(int)$val['ts'  ]] = 0);
				if (isset($val['dist'])) $tmp['time']['dist'][(int)$val['ts'  ]] = (float)$val['dist'];
				if (isset($val['ele' ])) $tmp['time']['ele' ][(int)$val['ts'  ]] = (float)$val['ele' ];
				if (!empty($val['hr' ])) $tmp['time']['hr'  ][(int)$val['ts'  ]] = (float)$val['hr'  ];
				if (isset($val['spd' ])) $tmp['time']['spd' ][(int)$val['ts'  ]] = (float)$val['spd' ];
			}
		}
		$data

		// */
		$json = json_encode($data);
		file_put_contents($cache_file, $json);
		// TODO set permissions
		return $json;
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

	static private function fillVal(array &$arr, $idx, $val) {
		Track::minmax($arr[$idx], $val);
	}

	static private function minmax(array &$minmax, $val) {
		if (!isset($minmax[1]) || $minmax[1] < $val) $minmax[1] = (float)$val;
//		if (!isset($minmax[2]) || $minmax[2] < $val) $minmax[2] = (float)$val;
	}
	static private function setValue(array &$data, array &$row, $type, $val) {
		if ($val == 'cad' && !isset($row[$val])) $row[$val] = 0;
		if (!isset($row[$val])) return;
		$ntype = $type == 'time' ? 'ts' : 'dist';
		if (!isset($row[$ntype])) return;
		$i = $row[$ntype];
		if (!isset($data[$type][$val][$i])) {
			$data[$type][$val][$i] = array($i * 1000, (float)$row[$val], 1);
			return;
		}
		$data[$type][$val][$i][1] += $row[$val];
		++$data[$type][$val][$i][2];
	}
	static private function simplifyData(array &$new_data, array &$data, $type, $val) {
		if (!isset($data[$type][$val])) return;
		if (!isset($new_data[$type])) $new_data[$type] = array();
		if (!isset($new_data[$type][$val])) $new_data[$type][$val] = array();
		while ($row = array_shift($data[$type][$val])) {
			$row[1] = $row[1] / $row[2];
			unset($row[2]);
			$new_data[$type][$val][] = $row;
		}
	}

}

?>
