<?php

/* This controller renders the page for a single track */

class TrackController {

	public function handleRequest() {

		if (empty($_GET['track'])) throw new Exception('No track given.');
		$track = $_GET['track'];
		
		// fetch requested track:
		$track = Track::find(array('id' => $track));
		if (empty($track)) throw new Exception(sprintf('Could not find track %s', $track));
		$track = array_shift($track);

		$parser = new GpxParser();
		$parser->setInput(DATA_DIR . '/' . $track->filename);
//		$parser->setDebug();
		$parser->parse();
		$data = $parser->getResult();

		
		render('track', array(
			'title'		=> $track->title,
			'track'		=> $track,
			'data'		=> $data
		));
	}
}

