<?php

/* This controller renders the home page */

class UploadController {
	public function handleRequest() {

		$upload = new Upload(DATA_DIR, 'files');

		if (!$upload->uploaded()) {
			return render('upload', array(
				'title'		=> 'Track upload',
			));
		}

		$error_msgs		= array();
		$success_msgs	= array();

		$cnt = $upload->count();
		for ($i = 0; $i < $cnt; ++$i) {

			$ret = $upload->store($i);
			if (!empty($ret)) {
				$error_msgs[] = $ret;
				continue;
			}

			$track = new Track();
			$track->title = $upload->basename($i);
			$track->filename = $upload->filename($i);
			$track->insert();

			$success_msgs[] = __(sprintf('<em>%s</em> was uploaded successfully!', $upload->orig_name($i)));
		}

		return render('upload', array(
			'title'		=> 'Track upload',
			'alerts'	=> array('success' => $success_msgs, 'error' => $error_msgs)
		));
	}
}

