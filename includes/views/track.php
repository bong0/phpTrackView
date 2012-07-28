<?php render('_header', array('title' => $title)) ?>
<div class="row">
	<div class="span12">
		<div class="chart" style="height: 600px" id="chart"></div>
	</div>
	<div class="span8">
		<div class="map" style="height: 400px" id="map"></div>
	</div>
</div>
<br/>
<script type="text/javascript">
	var map = new OpenLayers.Map('map', {
		projection: new OpenLayers.Projection("EPSG:900913"),
		displayProjection: new OpenLayers.Projection("EPSG:4326"),
		units: "m",
		numZoomLevels: 18,
	});
	var osm   = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
	map.addLayer(osm);
/*
	var hikebike = new OpenLayers.Layer.TMS(
		"Hike & Bike Map",
		"http://toolserver.org/tiles/hikebike/",
		{
			type: 'png',
			getURL: osm_getTileURL,
			displayOutsideMaxExtent: true,
			isBaseLayer: true,
			attribution: 'Map Data from <a href="http://www.openstreetmap.org/">OpenStreetMap</a> (<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-by-SA 2.0</a>)'
		}
	);
	map.addLayer(hikebike);
 */
	var hill = new OpenLayers.Layer.TMS(
		"Hillshading (NASA SRTM3 v2)",
		"http://toolserver.org/~cmarqu/hill/",
		{
			type: 'png', getURL: osm_getTileURL,
			displayOutsideMaxExtent: true, isBaseLayer: false,
			transparent: true, "visibility": true
		}
	);
	map.addLayer(hill);
//	map.zoomToMaxExtent();
	var lon = 8.61173;
	var lat = 50.0839;
	var zoom = 12;

	var lonLat = new OpenLayers.LonLat(lon, lat).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());
	if (!map.getCenter()) map.setCenter (lonLat, zoom);

	var vals = <?php echo $data ?>;
	var chart;
$(document).ready(function() {
	chart = new Highcharts.StockChart({
		chart: {
			renderTo: 'chart',
			type: 'line',
			zoomType: 'x',
		},
		title: {
			text: 'Data / time'
		},
		subtitle: {
			text: ''
		},
		xAxis: {
/*
			type: 'datetime',
			dateTimeLabelFormats: { // don't display the dummy year
				month: '%e. %b',
				year: '%b'
			}
*/
		},
		plotOptions: {
			line: {
				marker: {
					enabled: false,
				},
//				step: true,
			},
		},
		rangeSelector: {
			buttons: [{
				type:'minute',
				count: 60,
				text: '1h',
			},
			{
				type:'minute',
				count: 30,
				text: '30m',
			},
			{
				type:'minute',
				count: 10,
				text: '10m',
			},
			{
				type:'minute',
				count: 1,
				text: '1m',
			}],
			inputEnabled: false,
		},
		yAxis: [{
			title: { text: 'Elevation (in m)' },
			allowDecimals: false,
			startOnTick: false,
			endOnTick: false,
			maxPadding: 0,
			top: 100,
			height: 230,
			offset: 30,
			min: 0,
		},
		{
			title: { text: 'Speed' },
			allowDecimals: false,
			startOnTick: false,
			endOnTick: false,
			maxPadding: 0,
			top: 100,
			height: 230,
			offset: 80,
			min: 0,
		},
		{
			title: { text: 'Heart rate / Cadence' },
			allowDecimals: false,
			startOnTick: false,
			endOnTick: false,
			maxPadding: 0,
			top: 360,
			height: 100,
			offset: 30,
			min: 0,
		}],
/*		tooltip: {
			formatter: function() {
					return '<b>'+ this.series.name +'</b><br/>'+
					Highcharts.dateFormat('%e. %b', this.x) +': '+ this.y +' m';
			}
		},
*/
		
		series: [ 
		{
			type: 'area',
			name: 'Elevation',
			data: vals['time']['ele'],
			yAxis:0,
		},
		{
			name: 'Speed',
			data: vals['time']['spd'],
			yAxis:1,
		},
		{
			name: 'Cadence',
			data: vals['time']['cad'],
			yAxis:2,
		},
		{
			name: 'Heart rate',
			data: vals['time']['hr'],
			yAxis:2,
		}]
	});
});
</script>
<div class="row">
	<div class="span12">
		<pre><?php print_r($data) ?></pre>
	</div>
</div>
<?php render('_footer')?>
