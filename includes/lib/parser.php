<?php
# Tested with PHP v5.4.4
error_reporting(E_ALL | E_STRICT);

define("OUTPUT_PRECISION", 4);

class GpxParser {
  private $state; # holds state of parser, location in document
  private $timezone; #the timezone in format "Region/Capital"
  private $xmlp; #the underlying xml parser
  private $inputFile; #holds fd to input file
  private $output; #holds generated output in array form
	private $curTrkSeg; #holds currently processed trackSegment
	private $curPoint; #holds currently processed trackpoint
	private $trkInfo; #contains metadata of current trackSegment
  private $inputFormat; #string indicating wheter the input file is compressed or not

  private $procData; #array which holds read values and counters for calculations 
  # containers for data used by speed, distance and elevation gain/loss
  
  public function __construct($inputFile=null){
	$this->output = null; #is filled later
	$this->trkInfo = null;
	$this->state = new ParserState();
	$this->intputFormat = 'plain';
	$this->timezone = 'UTC';
	date_default_timezone_set($this->timezone); #set timezone

	$this->procData = array(
		'curTrackName' => '', # trackname needs to be built together over multiple function calls
		'locationCache' => array( new Location(), new Location() ),
		'distanceDelta' => 0,
		'totalDistance' => 0,
		'elevationCache' => new DiffCache(),
		'elevationGain' => 0,
		'elevationLoss' => 0,
		'timeCache' => new DiffCache(),
		'trackPointsProcessed' => 0,
		'currentSpeed' => 0,
		'cumulatedSpeed' => 0,
		'duration' => array('vPos' => 0, 'vAll' => 0),
		'cad_avg' => 0,
		'hr_avg' => 0
	);
	
	$this->xmlp = null; #inited later in parse() when encoding is known
	if(isset($inputFile)){
	  $this->setInput($inputFile);
	}
  }
  # public interfaces
  public function setInput($inputFile){
	$ext = pathinfo($inputFile);
	$ext = $ext['extension'];
	if($ext === "bz2" || $ext === "bzip2"){
	  $this->intputFormat = 'bz2';
	  $fp = bzopen($inputFile, 'r');
	}
	else if($ext === "gz"){
	  $this->intputFormat = 'gz';
	  $fp = gzopen($inputFile, 'r');
	}
	else {
	  $this->intputFormat = 'plain';
	  $fp = fopen($inputFile, "r");
	}
	if(!$fp){
	  die("could not open GPX input"); #bail out on error reading file
	}
	$this->inputFile = $fp; #copy file descriptor if successful
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
	else if($name === "GPXTPX:CAD") $this->put_cad();  #step frequency (garmin specific, TrackPointExtension)
  }

  private function onEndTag($parser, $name) {
	if($name === "TRK") $this->trackEnd(); # close track, finish parsing
	else if($name === "TRKSEG") $this->trackSegmentEnd(); #close track segment
	else if($name === "TRKPT") $this->end_newPoint(); # end point
	else if($name === "NAME") $this->state->in_name = 0; # unset trackname flag but do not push to info yet
	#all other closing functions are omitted, we unset the $in_* flags after having read the >data<
  }

  # root(track)-tag handlers
  private function trackBegin(){
	$this->state->in_trk = 1;
	$this->output = array();
  }
  private function trackEnd(){
	$this->state->in_trk = 0;
  }
  private function trackSegmentBegin(){
	end($this->output); #jmp to end
	$this->output[] = array(); #append new, empty array for tracksegment
	end($this->output); #jmp to end
	$this->curTrkSeg = &$this->output[key($this->output)]; #set current track segment

	$this->procData['totalDistance'] = 0; #reset distance
	$this->procData['cumulatedSpeed'] = 0;
  }
  private function trackSegmentEnd(){
	end($this->curTrkSeg);
	$this->curTrkSeg['info'] = array(); #(re)init info array for metadata
	$this->trkInfo = &$this->curTrkSeg['info'];

	$this->put_trackname($data=null, $endTag=true);
	$this->put_avgSpeed();
	
	if($this->procData['elevationGain'] > 0 || $this->procData['elevationLoss'] > 0){
	  $this->put_elevationStats();
	}
	if($this->procData['duration']['vAll'] > 0){
	  $this->put_durationStats();
	}
	if($this->procData['hr_avg'] > 0){
	  $this->put_avgHr();
	}
	if($this->procData['cad_avg'] > 0){
	  $this->put_avgCad();
	}
	$this->put_trackPointsProcessed();
  }

  # handlers for each point of a track
  private function begin_newPoint() {
	end($this->curTrkSeg);
 	$this->curTrkSeg[] = array(); #open new array for point data
	end($this->curTrkSeg);
	$this->curPoint = &$this->curTrkSeg[key($this->curTrkSeg)]; #set current point
  }
  private function end_newPoint() {
	  if($this->procData['locationCache'][1]->getLatitude()){ # calculate distance as soon as we have parsed > 1 waypoint
		  $this->procData['distanceDelta'] = $this->procData['locationCache'][1]->getDistToPoint($this->procData['locationCache'][0]);
		  $this->procData['totalDistance'] += $this->procData['distanceDelta'];
	  }
	  $this->put_dist($this->procData['totalDistance']);
	  $this->put_speed();

	  $this->procData['trackPointsProcessed'] += 1;
	}

  # metadata handling
  private function put_trackname($data=null, $tagEnd=false){

	if(!isset($data) && $tagEnd){ #reset flag only on request, not automatically like in any other put_ func
	  $this->trkInfo['name'] = $this->procData['curTrackName'];
	  $this->procData['curTrackName'] = ""; #flush
	  return;
	}
	else if(!$this->state->in_name && !isset($data)){
	  $this->state->in_name=1;
	  return;
	}
	$this->procData['curTrackName'] .= $data; #append read data
  }

  # data-handlers
  private function put_time($data=null){
	if(!$this->state->in_time && !isset($data)){
	  $this->state->in_time=1;
	  return;
	}

	#date_default_timezone_set($this->getTZ($data)); #retreive timezone identifier from ISO8601 string
	$timestamp = strtotime($data);

	$this->curPoint['ts'] = $timestamp;
	$this->procData['timeCache']->push($timestamp); #save to cache

	if($this->procData['currentSpeed'] > 1){ #only count distance is tracker was moving significantly
	  $this->procData['duration']['vPos'] += $this->procData['timeCache']->getDiff();
	  $this->procData['duration']['vAll'] += $this->procData['timeCache']->getDiff();
	}
	else {
	  $this->procData['duration']['vAll'] += $this->procData['timeCache']->getDiff();
	}

	$this->state->in_time=0; #reset flag
	return;
  }
  private function put_lat($data=null){
	$this->curPoint['lat'] = $data;

	if($this->procData['locationCache'][0]->getLatitude() > 0){ #if slot [0] is filled, put data to [1]
	  $this->procData['locationCache'][1]->setLatitude($data);
	}
	else {
	  $this->procData['locationCache'][0]->setLatitude($data);
	}

	$this->state->in_lat=0;
	return;
  }
  private function put_lon($data=null){
	$this->curPoint['lon'] = $data;

	if($this->procData['locationCache'][0]->getLongitude() > 0){#if slot [0] is filled, put data to [1]
	  $this->procData['locationCache'][1]->setLongitude($data);
	}
	else {
	  $this->procData['locationCache'][0]->setLongitude($data);
	}

	$this->state->in_lon=0; #reset flag
	return;
  }
  private function put_ele($data=null){
	if(!$this->state->in_ele && !isset($data)){
	  $this->state->in_ele=1;
	  return;
	}

	$this->curPoint['ele'] = $data;

	$this->procData['elevationCache']->push($data); #save to cache
	$elevationDiff = $this->procData['elevationCache']->getDiff();
	#if($this->distanceDelta<=0.001){ #only accept elevation deltas if distance delta to last point is > 1m
	  if($elevationDiff>=0){
		$this->procData['elevationGain'] = padd($this->procData['elevationGain'], $elevationDiff);
	  }
	  else {
		$this->procData['elevationLoss'] = psub($this->procData['elevationLoss'], $elevationDiff);
	  }
	#}
	$this->state->in_ele=0; #reset flag
	return;
  }
  private function put_cad($data=null){
	if(!$this->state->in_cad && !isset($data)){
	  $this->state->in_cad=1;
	  return;
	}
	
	if($data>0){
	  $this->procData['cad_avg'] = padd($this->procData['cad_avg'], $data);
	}
	$this->curPoint['cad'] = $data;

	$this->state->in_cad=0; #reset flag
	return;
  }
  private function put_hr($data=null){
	if(!$this->state->in_hr && !isset($data)){
	  $this->state->in_hr=true;
	  return;
	}
	
	if($data > 0){
	  $this->procData['hr_avg'] = padd($this->procData['hr_avg'], $data);
	}
	$this->curPoint['hr'] = $data;

	$this->state->in_hr=0; #reset flag
	return;
  }

  private function put_dist($data){
	$this->curPoint['dist'] = round(xpnd($data),OUTPUT_PRECISION);

	if($data > 0){ #make sure we have a distance != 0, only then we can shift
	  #move index [1] to [0] => like a shift register | use shift? FIXME
	  $this->procData['locationCache'][0] = $this->procData['locationCache'][1];
	  $this->procData['locationCache'][1] = new Location();
	}
  }

  private function put_speed(){
	$speed = 0; #in km/h

	if($this->procData['totalDistance'] > 0){
	  $timeDelta = pdiv($this->procData['timeCache']->getDiff(), 3600); # in hours
	  if($timeDelta > 0){ #we can't divide through zero
		$speed = pdiv($this->procData['distanceDelta'], $timeDelta);
			$this->curPoint['spd'] = round(xpnd($speed),OUTPUT_PRECISION);
		$this->procData['cumulatedSpeed'] += xpnd($speed);
		$this->procData['currentSpeed'] = xpnd($speed);
	  }
	}
  }

  private function put_avgSpeed(){
	$avgSpdMov = pdiv($this->procData['totalDistance'], pdiv($this->procData['duration']['vPos'], 3600));
	$avgSpdAll = pdiv($this->procData['totalDistance'], pdiv($this->procData['duration']['vAll'], 3600));

	if($avgSpdMov > 0){
	  $this->trkInfo['avgSpdMov'] = round($avgSpdMov,OUTPUT_PRECISION);
	}
	if($avgSpdAll > 0){
	  $this->trkInfo['avgSpdAll'] = round($avgSpdAll,OUTPUT_PRECISION);
	}

  }

  private function put_elevationStats(){
	$this->trkInfo['eleGain'] = round($this->procData['elevationGain'],OUTPUT_PRECISION);
	$this->trkInfo['eleLoss'] = round($this->procData['elevationLoss'],OUTPUT_PRECISION);
  }

  private function put_trackPointsProcessed(){
	$this->trkInfo['wptproc'] = $this->procData['trackPointsProcessed'];
  }

  private function put_durationStats(){
	$this->trkInfo['durationVpos'] = gmdate("H:i:s", $this->procData['duration']['vPos']);
	$this->trkInfo['durationVall'] = gmdate("H:i:s", $this->procData['duration']['vAll']);
  }
  
  private function put_avgCad(){
	$this->trkInfo['cadAvg'] = round(pdiv($this->procData['cad_avg'], $this->procData['trackPointsProcessed']),OUTPUT_PRECISION);
  }

  private function put_avgHr(){
	$this->trkInfo['hrAvg'] = round(pdiv($this->procData['hr_avg'], $this->procData['trackPointsProcessed']),OUTPUT_PRECISION);
  }

  private function onData($parser, $data){
	if($this->state->in_time) $this->put_time($data);
	else if($this->state->in_ele) $this->put_ele($data);
	else if($this->state->in_cad) $this->put_cad($data);
	else if($this->state->in_hr) $this->put_hr($data);
	else if($this->state->in_name) $this->put_trackname($data);
  }

  private function iRead($filename, $mode){ #wrapper for fread which considers compressed files
	if($this->inputFormat === 'bz2')
	  return bzread($filename, $mode);
	else if($this->inputFormat === 'gz')
	  return gzread($filename, $mode);
	else
	  return fread($filename, $mode);
  
  }
  private function iClose(){
	if($this->inputFormat === 'bz2')
	  return bzclose($this->inputFile);
	else if($this->inputFormat === 'gz')
	  return gzclose($this->inputFile);
	else
	  return fclose($this->inputFile);
  }
/*  private function getTZ($date){
	$pattern = '/[A-Z+-]+[0-9:]?[0-9]?$/';
	preg_match($pattern, $date, $matches, PREG_OFFSET_CAPTURE);
	 foreach($match in $matches){
	  $found = $match[0];
	  $pos = $match[1];

	  if(substr())
	}
  }*/
}

class ParserState {
  public $in_trk,
		 $in_trkseg,
		 $in_time,
		 $in_ele,
		 $in_cad, #cadence
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
