<?php

/* This controller renders the home page */

class HomeController {
	public function handleRequest() {
		
		// fetch all tracks:
		$tracks = Track::find();
		
		render('home', array(
			'title'		=> 'Track overview',
			'tracks'	=> $tracks
		));
	}
}

?>
