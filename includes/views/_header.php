<!DOCTYPE html>
<html>
	<head>
		<title><?php echo formatTitle($title)?></title> 
		<link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet">
		<link href="/css/style.css" rel="stylesheet">
	</head>
	<body>
<!--
-->
		<div class="navbar navbar-fixed-top clearfix">
			<div class="navbar-inner">
				<div class="container">
					<a class="brand" href="#">phpTrackView</a>
					<ul class="nav">
					  <li><a href="/">Tracks</a></li>
					  <li><a href="/upload">Upload</a></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="container">
			<?php if (!empty($title)) : ?>
			<div class="page-header">
				<h1><?php echo esc_html($title)?></h1>
			</div>
			<?php endif ?>
