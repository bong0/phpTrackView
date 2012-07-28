<!DOCTYPE html>
<html>
	<head>
		<title><?php echo formatTitle($title)?></title> 
		<link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet">
		<link href="/css/style.css" rel="stylesheet">
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
		<script src="http://code.highcharts.com/stock/1.1.4/highstock.js"></script>
<!--
		<script src="http://code.highcharts.com/2.2.0/highcharts.js"></script>
		<script src="http://code.highcharts.com/v2.3Beta/highcharts.js"></script>
		<script src="http://code.highcharts.com/v2.3Beta/highcharts-more.js"></script>
-->
		<script src="http://openlayers.org/api/2.12/OpenLayers.js"></script>
		<script src="http://www.openstreetmap.org/openlayers/OpenStreetMap.js"></script>
		<script src="/js/map.js"></script>
	</head>
	<body>
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
