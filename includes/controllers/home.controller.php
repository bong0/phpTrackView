<?php

/* This controller renders the home page */

class HomeController {
	public function handleRequest() {
		
		// Select all countries:
		$tracks = Track::find();
		
		render('home', array(
			'title'		=> 'Track overview',
			'tracks'	=> $tracks
		));
	}
}

?>
