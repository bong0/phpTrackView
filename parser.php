<?php
# Tested with PHP v5.4.4
error_reporting(E_ALL);

include_once("dist2points.php");
include_once('common.php');

# TODO
# hoehenmeter

class GpxParser {
  private $indentLevel; # to do proper output indentation
  private $state; # holds state of parser, location in document
  private $timezone; #the timezone in format "Region/Capital"
  private $xmlp; #the underlying xml parser
  private $inputFile; #holds fd to input file
  private $output; #holds generated output
  private $debug; #flag if debugging is enabled
  private $minify; #flag is minified output is on (yes by default)

  # containers for data used by speed, distance and elevation gain/loss
  private $locationCache; #holds the two last locations processed
  private $distanceDelta; # holds the delta of the last two trackpoints in km
  private $distance; #holds the cumulated distance between the waypoints of a track segment
  private $elevationCache; #holds the last two elevation values
  private $elevationGain; #holds the cumulated elevation gain in meters
  private $elevationLoss; #holds the cumulated elevation loss in meters
  private $timeCache; # holds the timestamps of the last two trackpoints in seconds
  private $trackPointsProcessed; #number of processed trackpoints (for averaging calcs)
  private $cumulatedSpeed; #an addition of all speeds in one track-segment
  
  public function __construct($inputFile=null,$debugging=0){
	if($debugging) $this->debug=1;
	else $this->debug=0;
	$this->minify = 1;
	$this->indentLevel = 0;
	$this->state = new ParserState();
	$this->locationCache = array( new Location(), new Location() );
	$this->distanceDelta = 0;
	$this->distance = 0;
	$this->elevationCache = new DiffCache();
	$this->elevationGain =
	$this->elevationLoss = 0;
	$this->timeCache = new DiffCache();
	$this->trackPointsProcessed = 0;
	$this->cumulatedSpeed = 0;
	$this->timezone = "Europe/Berlin"; #!!! needs to be read from wordpress api TODO
	date_default_timezone_set($this->timezone); #set timezone
	$this->xmlp = xml_parser_create(); #construct xmlparser
	  #register handlers
	  xml_set_element_handler($this->xmlp, "GpxParser::onStartTag", "GpxParser::onEndTag"); # register start end end tag handlers for our root ("trk")
	  xml_set_character_data_handler ($this->xmlp , "GpxParser::onData"); # register data handler (content between ">","<")
	if(isset($inputFile)){
	  $this->setInput($inputFile);
	}
  }

  # public interfaces
  public function setInput($inputFile){
	if (!($fp = fopen($inputFile, "r"))) {
	  die("could not open GPX input"); #bail out on error reading file
	}
	$this->inputFile = $fp; #copy file descriptor if successful
  }

  public function setDebug(){
	$this->debug = 1;
	$this->minify = 0;
	return;
  }

  public function parse($bufsize=4096){
	if(!isset($this->inputFile)){
	  die(print("You have to call setInput() before parsing!"));
	}
	while ($data = fread($this->inputFile, $bufsize)) {
	  if (!xml_parse($this->xmlp, $data, feof($this->inputFile))) {
		  die(sprintf("XML error: %s at line %d",
					  xml_error_string(xml_get_error_code($this->xmlp)),
					  xml_get_current_line_number($this->xmlp)));
	  }
	}
	xml_parser_free($this->xmlp);
  }

  public function getResult(){
	return $this->output;
  }

  private function printOut($str){
	if($this->minify){
	  $toReplace = array("\n","\t","\r\n"," ");
	  $replacement = "";
	  $str = str_replace($toReplace, $replacement, $str);
	  $this->output .= $str; #write output to mem
	}
	else {
	  print($str); #print immediately to stdout
	}
  }

  # internal parsing methods
  private function onStartTag($parser, $name, $attrs) {
	
	if($name === "TRK") $this->trackBegin();
	else if($name === "TRKSEG") $this->trackSegmentBegin(); # open new track segment
	else if($name === "NAME" && $this->state->in_trk) $this->put_trackname(); # track name
	else if($name === "TIME" && $this->state->in_trk) $this->put_time(); #date in format YYYY-MM-DDTHH:MM:SSZ | we don't need dates outside of our track
	else if($name === "ELE" && $this->state->in_trk) $this->put_ele(); #elevation in m

	else if($name === "TRKPT" && $this->state->in_trk){
	  $this->begin_newPoint();
	  foreach($attrs as $key => $value){ # loop through attributes of "trkpt"-tag 
		if($key === "LAT" && $this->state->in_trk) $this->put_lat($value); #latitude
		if($key === "LON" && $this->state->in_trk) $this->put_lon($value); #longitude
	  }
	}

	else if($name === "GPXTPX:HR") $this->put_hr();  #heart-rate (garmin specific, TrackPointExtension)
	else if($name === "GPXTPX:CAD") $this->put_cad();  #step frequency ?  (garmin specific, TrackPointExtension)
  }

  function onEndTag($parser, $name) {
	if($name === "TRK") $this->trackEnd(); # close track, finish parsing
	else if($name === "TRKSEG") $this->trackSegmentEnd(); #close track segment
	else if($name === "TRKPT") $this->end_newPoint(); # end point
	#all other closing functions are omitted, we unset the $in_* flags after having read the >data<
  }

  # internal output helpers
  private function indent($direction=""){ # give current indent level to stdout, raise or lower it
	$this->indentLevel;
	if($direction=="-") $this->indentLevel--;
	for($i=0; $i < $this->indentLevel; $i++) $this->printOut("\t");
	if($direction=="+") $this->indentLevel++;
  }
  private function openArray($prefix=""){
	$this->indent("+");
	if($prefix){
	  $this->printOut($prefix);
	}
	$this->printOut("array(\n");
  }
  private function closeArray($isSubArray=1){ #flag to indicate if array is part of another array or not
	$this->indent("-");
	if($isSubArray)
		$this->printOut("),\n");
	else
	  $this->printOut(");\n");
  }

  # root(track)-tag handlers
  private function trackBegin(){
	$this->state->in_trk = 1;
	$this->openArray("\$gpxdata = ");
  }
  private function trackEnd(){
	$this->state->in_trk = 0;
	$this->closeArray(false); #this one is a standalone array in output (the $gpxdata variable)
  }
  private function trackSegmentBegin(){
	$this->openArray();
	$this->distance = 0; #reset distance
  }
  private function trackSegmentEnd(){
	$this->put_avgSpeed();
	$this->closeArray();
  }

  # handlers for each point of a track
  private function begin_newPoint() { $this->openArray(); } # open new array
  private function end_newPoint() {
	  if($this->locationCache[1]->getLatitude()){ # calculate distance as soon as we have parsed > 1 waypoint
		  $this->distanceDelta = $this->locationCache[1]->getDistToPoint($this->locationCache[0]);
		  $this->distance += $this->distanceDelta;
	  }
	  $this->put_dist($this->distance);
	  $this->put_speed();

	  $this->trackPointsProcessed++;
	  $this->closeArray(); # close array
	}

  # metadata handling
  private function put_trackname($data=null){
	if(!$this->state->in_name && !isset($data)){
	  $this->state->in_name=1;
	  return;
	}

	$this->indent();
	$this->printOut("'name' => '".$data."',\n");
	$this->state->in_name=0;
  }

  # data-handlers
  private function put_time($data=null){
	if(!$this->state->in_time && !isset($data)){
	  $this->state->in_time=1;
	  return;
	}
	$timestamp = strtotime($data);

	$this->indent();
	$this->printOut("'ts' => '".$timestamp."',\n");
	$this->timeCache->push($timestamp); #save to cache

	$this->state->in_time=0; #reset flag
	return;
  }
  private function put_lat($data=null){
	$this->indent();
	$this->printOut("'lat' => '".$data."',\n");

	if($this->locationCache[0]->getLatitude() > 0){ #if slot [0] is filled, put data to [1]
	  $this->locationCache[1]->setLatitude($data);
	}
	else {
	  $this->locationCache[0]->setLatitude($data);
	}

	$this->state->in_lat=0;
	return;
  }
  private function put_lon($data=null){
	$this->indent();
	$this->printOut("'lon' => '".$data."',\n");

	if($this->locationCache[0]->getLongitude() > 0){#if slot [0] is filled, put data to [1]
	  $this->locationCache[1]->setLongitude($data);
	}
	else {
	  $this->locationCache[0]->setLongitude($data);
	}

	$this->state->in_lon=0; #reset flag
	return;
  }
  private function put_ele($data=null){
	if(!$this->state->in_ele && !isset($data)){
	  $this->state->in_ele=1;
	  return;
	}

	$this->indent();
	$this->printOut("'ele' => '".$data."',\n");

	$this->elevationCache->push($data); #save to cache
	$elevationDiff = $this->elevationCache->getDiff();
	if($elevationDiff>=0){
	  $this->elevationGain += $elevationDiff;
	}
	else {
	   $this->elevationLoss += $elevationDiff;
	}

	$this->state->in_ele=0; #reset flag
	return;
  }
  private function put_cad($data=null){
	if(!$this->state->in_cad && !isset($data)){
	  $this->state->in_cad=1;
	  return;
	}

	$this->indent();
	$this->printOut("'cad' => '".$data."',\n");

	$this->state->in_cad=0; #reset flag
	return;
  }
  private function put_hr($data=null){
	if(!$this->state->in_hr && !isset($data)){
	  $this->state->in_hr=true;
	  return;
	}

	$this->indent();
	$this->printOut("'hr' => '".$data."',\n");

	$this->state->in_hr=0; #reset flag
	return;
  }

  private function put_dist($data){
	$this->indent();
	$this->printOut("'dist' => '".xpnd($data)."',\n");

	if($data > 0){ #make sure we have a distance != 0, only then we can shift
	  #move index [1] to [0] => like a shift register | use shift? FIXME
	  $this->locationCache[0] = $this->locationCache[1];
	  $this->locationCache[1] = new Location();
	}
  }

  private function put_speed(){
	$this->indent();
	$speed = 0; #in km/h

	if($this->distance > 0){
	  $timeDelta = pdiv($this->timeCache->getDiff(), 3600); # in hours
	#  echo $timeDelta."\n";
	#  echo $this->distanceDelta."\n";
	  $speed = pdiv($this->distanceDelta, $timeDelta);
	}
	$this->printOut("'spd' => '".xpnd($speed)."',\n");
	$this->cumulatedSpeed += xpnd($speed);
  }

  private function put_avgSpeed(){
	 $this->printOut("'avgSpd' => '".pdiv($this->cumulatedSpeed, $this->trackPointsProcessed)."',\n");
  }

  function onData($parser, $data){
	if($this->state->in_time) $this->put_time($data);
	else if($this->state->in_ele) $this->put_ele($data);
	else if($this->state->in_cad) $this->put_cad($data);
	else if($this->state->in_hr) $this->put_hr($data);
	else if($this->state->in_name) $this->put_trackname($data);
  }
}

class ParserState {
  public $in_trk,
		 $in_trkseg,
		 $in_time,
		 $in_ele,
		 $in_cad,
		 $in_hr,
		 $in_name;
  public function  __construct(){
	$in_trk	= $in_trkseg = $in_time= $in_ele = $in_cad = $in_hr = $in_name = 0;
#	foreach($this as $key => $value) {
#	  get_object_vars($this)[$key] = 0; #initialize all attribs with 0 
#	}
  }
}


class Location {
  # attributes
  private $lat;
  private $lon;

  public function __construct(){
	$this->lat = $this->lon = 0;
  }
  # getters and setters
  public function setLatitude($value){
	$this->lat = $value;
	return;
  }
  public function setLongitude($value){
	$this->lon = $value;
	return;
  }
   public function getLatitude(){
	return $this->lat;
  }
  public function getLongitude(){
	return $this->lon;
  }

  # normal methods
  public function getDistToPoint($location){
	return getDistance($this->lat, $this->lon, $location->getLatitude(), $location->getLongitude());
  }
  
}

class DiffCache {
  private $valCache;
  private $default_val; # default value for initialization of array elements
  private $default_needs_init; # flag indicating wether the default value needs allocation via "new"
  private $diff;

  public function __construct($default=0){ #1st parameter specifies the value which will be used to initialize the array's elements 
	$this->valCache = array( $default, $default );
	$this->default_val = $default;
	$this->default_needs_init = class_exists($this->default_val); #check wether default_val is a valid class
	$this->diff = 0;
  }
  public function push($value){
	if(!$this->valCache[0]){ # first entry in array
	  $this->valCache[0] = $value;
	}
	else if($this->valCache[1]){ # we need to shift left

	  $this->valCache[0] = $this->valCache[1];

		if($this->default_needs_init){
		  $this->valCache[1] = new $value();
		}
		else {
		  $this->valCache[1] = $value;
		}
		$this->diff = psub($this->valCache[1], $this->valCache[0]); #save diff after shift
	}
	else { #regular fill of second element
	  if($this->default_needs_init){
		 $this->valCache[1] = new $value();
	  }
	  else {
		$this->valCache[1] = $value;
	  }
	   $this->diff = psub($this->valCache[1], $this->valCache[0]); #generate diff
	}
  }
  public function getDiff(){
	return $this->diff;
  }
  public function getElement($index){
	if($index > 1 || $index < 0){
	  die(print("getElement failed, index is not 0 or 1!"));
	}
	else {
	  return $this->valCache[$index];
	}
  }
}
?>
