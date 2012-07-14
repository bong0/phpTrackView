<?php
header("Content-Type: text/html; charset=utf-8");

try {
	require_once 'includes/main.php';


	$action = isset($_GET['action']) ? $_GET['action'] : NULL;

	switch (true) {
		case $action == 'upload':	$c = new UploadController();	break;
		case $action == 'track':	$c = new TrackController();		break;
		case empty($_GET):			$c = new HomeController();		break;
		default:					throw new Exception('Wrong page!');
	}
	$c->handleRequest();
}
catch(Exception $e) {
	render('error', array('message' => $e->getMessage()));
}

