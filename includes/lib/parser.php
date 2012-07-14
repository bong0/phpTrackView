<?php
# Tested with PHP v5.4.4
error_reporting(E_ALL);

include_once("dist2points.php");
include_once('common.php');

define("OUTPUT_PRECISION", 4);

class GpxParser {
  private $indentLevel; # to do proper output indentation
  private $state; # holds state of parser, location in document
  private $timezone; #the timezone in format "Region/Capital"
  private $xmlp; #the underlying xml parser
  private $inputFile; #holds fd to input file
  private $output; #holds generated output
  private $debug; #flag if debugging is enabled
  private $minify; #flag is minified output is on (yes by default)
  private $inputBzip2; #flag indicating wheter the input file is compressed using bz2

  private $curTrackName; # trackname needs to be built together over multiple function calls

  # containers for data used by speed, distance and elevation gain/loss
  private $locationCache; #holds the two last locations processed
  private $distanceDelta; # holds the delta of the last two trackpoints in km
  private $distance; #holds the cumulated distance between the waypoints of a track segment
  private $elevationCache; #holds the last two elevation values
  private $elevationGain; #holds the cumulated elevation gain in meters
  private $elevationLoss; #holds the cumulated elevation loss in meters
  private $timeCache; # holds the timestamps of the last two trackpoints in seconds
  private $trackPointsProcessed; #number of processed trackpoints (for averaging calcs)
  private $currentSpeed; #hence the name
  private $cumulatedSpeed; #an addition of all speeds in one track-segment
  private $duration; #holds array of track durations (stops in- and excluded)
  
  public function __construct($inputFile=null,$debugging=0){
	if($debugging) $this->debug=1;
	else $this->debug=0;
	$this->minify = 1;
	$this->indentLevel = 0;
	$this->state = new ParserState();
	$this->inputBzip2 = false;
	$this->curTrackName = "";
	$this->locationCache = array( new Location(), new Location() );
	$this->distanceDelta = 0;
	$this->distance = 0;
	$this->elevationCache = new DiffCache();
	$this->elevationGain =
	$this->elevationLoss = 0;
	$this->timeCache = new DiffCache();
	$this->trackPointsProcessed = 0;
	$this->currentSpeed = 0;
	$this->cumulatedSpeed = 0;
	$this->duration = array('vPos'=>0, 'vAll'=>0);
	$this->timezone = "UTC";
	date_default_timezone_set($this->timezone); #set timezone
	$this->xmlp = null; #inited later in parse() when encoding is known
	if(isset($inputFile)){
	  $this->setInput($inputFile);
	}
  }
  # public interfaces
  public function setInput($inputFile){
	$ext = pathinfo($inputFile)['extension'];
	if($ext === "bz2" || $ext === "bzip2" || $this->inputBzip2){
	  $this->inputBzip2 = true;
	  $fp = bzopen($inputFile, "r");
	}
	else {
	  $fp = fopen($inputFile, "r");
	}
	if(!$fp){
	  die("could not open GPX input"); #bail out on error reading file
	}
	$this->inputFile = $fp; #copy file descriptor if successful
  }

  public function setDebug(){
	$this->debug = 1;
	$this->minify = 0;
	return;
  }
  public function setBz2(){
	$this->inputBzip2 = true;
	return;
  }

  public function parse($bufsize=4096){
	if(!isset($this->inputFile)){
	  die(print("You have to call setInput() before parsing!"));
	}

	$this->xmlp = xml_parser_create(); #construct xmlparser
	xml_parser_set_option($this->xmlp, XML_OPTION_SKIP_WHITE, 1);

	#register handlers
	xml_set_element_handler($this->xmlp, "GpxParser::onStartTag", "GpxParser::onEndTag"); # register start end end tag handlers for our root ("trk")
	xml_set_character_data_handler ($this->xmlp , "GpxParser::onData"); # register data handler (content between ">","<")
	
	while ($data = $this->iRead($this->inputFile, $bufsize)) {
	  if (!xml_parse($this->xmlp, $data, feof($this->inputFile))) {
		  die(sprintf("XML error: %s at line %d",
					  xml_error_string(xml_get_error_code($this->xmlp)),
					  xml_get_current_line_number($this->xmlp)));
	  }
	}

	xml_parser_free($this->xmlp);
	$this->iClose($this->inputFile);
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
	else if($name === "NAME") $this->put_trackname(); # track name
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

  private function onEndTag($parser, $name) {
	if($name === "TRK") $this->trackEnd(); # close track, finish parsing
	else if($name === "TRKSEG") $this->trackSegmentEnd(); #close track segment
	else if($name === "TRKPT") $this->end_newPoint(); # end point
	else if($name === "NAME") $this->put_trackname($data=null, $endTag=true); # finish trackname
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
	$this->put_elevationStats();
	$this->put_trackPointsProcessed();
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
  private function put_trackname($data=null, $tagEnd=false){
	if(!$this->state->in_name && !isset($data)){
	  $this->state->in_name=1;
	  return;
	}
	else if(!isset($data) && $tagEnd){ #reset flag only on request, not automatically like in any other put_ func
	  $this->indent();
	  $this->printOut("'name' => '".$this->curTrackName."',\n");
	  $this->curTrackName = ""; #flush
	  $this->state->in_name=0;
	  return;
	}
	$this->curTrackName .= $data;
  }

  # data-handlers
  private function put_time($data=null){
	if(!$this->state->in_time && !isset($data)){
	  $this->state->in_time=1;
	  return;
	}

	#date_default_timezone_set($this->getTZ($data)); #retreive timezone identifier from ISO8601 string
	$timestamp = strtotime($data);

	$this->indent();
	$this->printOut("'ts' => '".$timestamp."',\n");
	$this->timeCache->push($timestamp); #save to cache

	if($this->currentSpeed > 0){
	  $this->duration['vPos'] += $this->timeCache->getDiff();
	  $this->duration['vAll'] += $this->timeCache->getDiff();
	}
	else {
	  $this->duration['vAll'] += $this->timeCache->getDiff();
	}

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
	if($this->distanceDelta<=0.001){ #only accept elevation deltas if distance delta to last point is > 1m
	  if($elevationDiff>=0){
		$this->elevationGain += $elevationDiff;
	  }
	  else {
		$this->elevationLoss -= $elevationDiff;
	  }
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
	$this->printOut("'dist' => '".round(xpnd($data),OUTPUT_PRECISION)."',\n");

	if($data > 0){ #make sure we have a distance != 0, only then we can shift
	  #move index [1] to [0] => like a shift register | use shift? FIXME
	  $this->locationCache[0] = $this->locationCache[1];
	  $this->locationCache[1] = new Location();
	}
  }

  private function put_speed(){
	$speed = 0; #in km/h

	if($this->distance > 0){
	  $timeDelta = pdiv($this->timeCache->getDiff(), 3600); # in hours
	 # echo $timeDelta."\n";
	 # echo $this->distanceDelta."\n";
	  if($timeDelta > 0){ #we can't divide through zero
		$speed = pdiv($this->distanceDelta, $timeDelta);
		$this->indent();
		$this->printOut("'spd' => '".round(xpnd($speed),OUTPUT_PRECISION)."',\n");
		$this->cumulatedSpeed += xpnd($speed);
		$this->currentSpeed = xpnd($speed);
	  }
	}
  }

  private function put_avgSpeed(){
	$avgSpd = pdiv($this->cumulatedSpeed, $this->trackPointsProcessed);
	if($avgSpd > 0){
	  $this->indent();
	  $this->printOut("'avgSpd' => '".round($avgSpd,OUTPUT_PRECISION)."',\n");
	}
  }

  private function put_elevationStats(){
	$this->indent();
	$this->printOut("'eleGain' => '".round($this->elevationGain,OUTPUT_PRECISION)."',\n");
	$this->indent();
	$this->printOut("'eleLoss' => '".round($this->elevationLoss,OUTPUT_PRECISION)."',\n");
  }

  private function put_trackPointsProcessed(){
	$this->indent();
	$this->printOut("'wptproc' => '".round($this->trackPointsProcessed, OUTPUT_PRECISION)."',\n");
  }

  private function put_durationStats(){
	$this->indent();
	$this->printOut("'durationVpos' => '".gmdate("H:i:s", $duration['vPos'])."',\n");
	$this->printOut("'durationVall' => '".gmdate("H:i:s", $duradion['vAll'])."',\n");
  }

  private function onData($parser, $data){
	if($this->state->in_time) $this->put_time($data);
	else if($this->state->in_ele) $this->put_ele($data);
	else if($this->state->in_cad) $this->put_cad($data);
	else if($this->state->in_hr) $this->put_hr($data);
	else if($this->state->in_name) $this->put_trackname($data);
  }

  private function iRead($filename, $mode){ #wrapper for fread which considers compressed files
	if($this->inputBzip2)
	  return bzread($filename, $mode);
	else
	  return fread($filename, $mode);
  }
  private function iClose(){
	if($this->inputBzip2)
	  return bzclose($this->inputFile);
	else
	  return bzclose($this->inputFile);
  }
 # private function getTZ($date){
#	$pattern = '/[A-Z+-]+[0-9:]?[0-9]?$/';
#	preg_match($pattern, $date, $matches, PREG_OFFSET_CAPTURE);
	#foreach($match in $matches){
#	  $found = $match[0];
#	  $pos = $match[1];

#	  if(substr())
#	}
 # }
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