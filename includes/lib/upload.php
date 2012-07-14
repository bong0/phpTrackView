<?php

class Upload {

	private	$uploadDir;
	private $fieldName;
	private	$files	= array();

	public function uploaded(){ return count($this->files) > 0; }
	public function count()   { return count($this->files); }


	public function __construct($upload_dir, $field_name) {
		$this->uploadDir = $upload_dir;
		$this->fieldName = $field_name;

		if (empty($_FILES[$this->fieldName])) return;

		$files			= $_FILES['files'];		
		$count			= count($files['tmp_name']);

		for ($i = 0; $i < $count; ++$i) {
			$this->files[] = array(
				'orig_name'	=> $files['name'][$i],
				'tmp_name'	=> $files['tmp_name'][$i],
				'error'		=> $files['error'][$i],
				'size'		=> $files['size'][$i],
				'type'		=> $files['type'][$i]
			);

		}
	}


	public static function maxFileSize() {
		return min((int)ini_get('upload_max_filesize'), (int)ini_get('post_max_size'));
	}

	public static function maxPostSize() {
		return (int)ini_get('post_max_size');
	}

	public static function maxFileUploads() {
		return (int)ini_get('max_file_uploads');
	}

	// TODO max_input_time

	public function basename($idx) {
		if ($idx >= $this->count()) throw new Exception('Bad use of class Upload.');
		return $this->files[$idx]['basename'];
	}

	public function filename($idx) {
		if ($idx >= $this->count()) throw new Exception('Bad use of class Upload.');
		return $this->files[$idx]['filename'];
	}

	public function orig_name($idx) {
		if ($idx >= $this->count()) throw new Exception('Bad use of class Upload.');
		return $this->files[$idx]['orig_name'];
	}

	public function store($idx) {
		if ($idx >= $this->count()) throw new Exception('Bad use of class Upload.');
		$orig_name	= $this->files[$idx]['orig_name'];
		$tmp_name	= $this->files[$idx]['tmp_name'];

		switch (UPLOAD_TYPE) {
			case 'bz': $orig_name .= '.bz2'; break;
			case 'gz': $orig_name .= '.gz'; break;
			default  : break;
		}

		if ($this->files[$idx]['error'] != 0) return $this->files[$idx]['error'];

		$filetype = check_filetype($this->files[$idx]['orig_name']);

		if (!$filetype['ext']) return 'Invalid file type';

		$filename	= unique_filename($this->uploadDir, $orig_name);
		$target		= $this->uploadDir . '/' . $filename;

		$this->files[$idx]['basename']	= basename($orig_name, '.' . $filetype['ext']); 
		$this->files[$idx]['mimetype']	= $filetype['type'];
		$this->files[$idx]['extension']	= $filetype['ext'];
		$this->files[$idx]['filename']	= $filename;
		$this->files[$idx]['path']		= $target;

		switch (UPLOAD_TYPE) {
			case 'bz': $this->bzmove($tmp_name, $target); break;
			case 'gz': $this->gzmove($tmp_name, $target); break;
			default  : move_uploaded_file($tmp_name, $target); break;
		}

		// Set correct file permissions
		$stat	= @stat(dirname($target));
		$perms	= $stat['mode'] & 0007777;
		$perms	= $perms & 0000666;
		@chmod($target, $perms);
		clearstatcache();

		return NULL; // indicates success - otherwise error message is returned
	}

	function bzmove($from, $to) {
		$plain	= fopen($from, 'r');
		$bz		= bzopen($to, 'w');

		while (!feof($plain)) {
			$data = fread($plain, 8192);
			$len = strlen($data);
			while ($len > 0) {
				$written = bzwrite($bz, $data);
				$data = substr($data, $written);
				$len -= $written;
			}
		}

		bzclose($bz);
		fclose($plain);
		unlink($from);
	}

	function gzmove($from, $to) {
		$plain	= fopen($from, 'r');
		$gz		= gzopen($to, 'w');

		while (!feof($plain)) {
			$data = fread($plain, 8192);
			$len = strlen($data);
			while ($len > 0) {
				$written = gzwrite($gz, $data);
				$data = substr($data, $written);
				$len -= $written;
			}
		}

		gzclose($gz);
		fclose($plain);
		unlink($from);
	}

}
