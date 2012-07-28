<?php

    class PathPoint {
        public $lat;
        public $lng;
        public $index;
 
        public function __construct($lat, $lng, $index){
            $this->index = $index;
            $this->lat = $lat;
            $this->lng = $lng;
        }
    }

    class Path
    {
        # array() of PathPoints contained in path
        private $points = array();
        # flags wheter or not $points needs to be sorted
        private $sortNeeded = false;
 
        #Add a point to the shape. Marks the list of points as out-of-order
        public function addPoint(PathPoint $pt){
            $this->points[] = $pt;
            $this->sortNeeded = true;
            return $this;
        }
 
		# init Path from passed tracksegment as php array
		public function fromTrkSeg($arr){
			foreach((array) $arr as $key => $pt){
				if($key === 'info') continue; #exclude non-point element info
				$this->addPoint(new PathPoint($pt['lat'], $pt['lon'], $key));
			}
		}
		
		# Return Path as track-segment php array
		public function toTrkSeg(){
			$path = array();
			foreach($this->points() as $key => $pt){
				$path[] = array('lat' => $pt->lat,
								'lon' => $pt->lng);
			}
			return $path;
		}
		
        # extended getter for $points which sorts the array if required 
        public function points(){
            if ($this->sortNeeded) {
                usort($this->points, array(__CLASS__, 'sort'));
                $this->sortNeeded = false;
            }
 
            return $this->points;
        }
 
        # sort callback to sort PathPoint by index
        public static function sort($a, $b){
            if ($a->index < $b->index)
				return -1;
            if ($a->index > $b->index)
				return 1;
            return 0; #items are equal
        }
    }
class PathSimplifier {
	
	##
	# Remove points from Path using the D.Peucker algorithm
	# arg2: tolerance in degrees (float)
	 
	public function simplify($path, $tolerance){
		# Paths with less than 2 points or an invalid tolerance will not be processed
		if ($tolerance <= 0 || count($path->points()) < 3) {
			return $path;
		}

		$points = $path->points();
		$newPath = new Path();

		$newPath->addPoint($points[0]); # add first item
		$newPath->addPoint($points[count($points)-1]); # add last item 

		# use 1st and last points of path as entry
		$this->dpSimplify(
			$path,			# orig. Path
			$newPath,		# simplified Path
			$tolerance,		# passed tolerance
			0,				# index of fist element in Path array
			count($points)-1	# index of last element in Path array
		);

		return $newPath;
	}
	
	/**
	 * Reduce multiple tracks and tracksegments and return php array
	 * comprising all tracks
	 *
	 * @param	array() #2-level
	 * 
	 * @return	array() #reduced tracks, preserves input structure
	 */
	public function simplifyMultiple($arr, $tolerance){
		$out = array();
		foreach($arr as $track){
			$out[] = array(); #create empty subarray for each track processed
			$trkCache = &$out[count($out)-1]; #set trackCache to last item in track list
			$trkSegCache = array(); # caches all processed tracksegments for current cycle
			
			foreach($track as $trackSeg){
				$path = new Path();
				$path->fromTrkSeg($trackSeg);
				$trkSegCache[] = $this->simplify($path, $tolerance)->toTrkSeg(); #put reduced trkSeg in cache
			
			}

			$trkCache[] = $trkSegCache; # put reduced tracksegments to current track
		}
		return $out;
	}
	

	# Simplify given Path, extend newPath recursively
	private function dpSimplify(Path $path,		# orig. Path
								Path $newPath, 	# simplified Path
								$tolerance,		# tolerance in deg
								$firstEl,		# first element index in Path arr
								$lastEl){		# last element index in Path arr
		if ($lastEl <= $firstEl + 1)
			return; #indexes overlap (our anchor)

		# copy list of points for simplicity
		$points = $path->points();
		
		
		/*  
		 * Loop from 1st index passed to last searching for the point with the max. orthogonal distance to 
		 */
		$maxDist = 0.0; # for caching max. dist to current pt
		$indexFarthest = 0; # contains index of point the furthest away
		$firstPoint = &$points[$firstEl]; # grab object references for first point
		$lastPoint = &$points[$lastEl]; # grab object references for last point

		for ($i = $firstEl + 1; $i < $lastEl; $i++) {
			$point = $points[$i]; #grab point object from current index

			$dist = $this->orthogonalDistance($point, $firstPoint, $lastPoint);

			# save the point with the greatest dist so far
			if ($dist > $maxDist) {
				$maxDist = $dist;
				$indexFarthest = $i;
			}
		}

		# determine if orth. distance is in tolerance, if no: keep else discard point
		if($maxDist > $tolerance) {
			$newPath->addPoint($points[$indexFarthest]);

			# now 'split' the task of simplification in the range between first and found element (farthest away)
			# and the range from the maxDist element to the end of the Path
			$this->dpSimplify($path, $newPath, $tolerance, $firstEl, $indexFarthest);
			$this->dpSimplify($path, $newPath, $tolerance, $indexFarthest, $lastEl);
		}
	}

	# calc distance of $point to the line connecting $lineStart and $lineEnd
	public function orthogonalDistance($point, $lineStart, $lineEnd)
	{
		$area = abs(
			pdiv(
				
				psub(
					psub(
						psub(
							padd(
								padd(
									pmul($lineStart->lat, $lineEnd->lng),
									pmul($lineEnd->lat, $point->lng)
								),
								pmul($point->lat, $lineStart->lng)
							),
							pmul($lineEnd->lat, $lineStart->lng)
						),
						pmul($point->lat, $lineEnd->lng)
					),
					pmul($lineStart->lat, $point->lng)
				),
				2
			)
		);

		$bottom = sqrt(pow(psub($lineStart->lat, $lineEnd->lat),2) + pow(psub($lineStart->lng, $lineEnd->lng),2));

		return pmul(pdiv($area, $bottom), 2.0);
	}
}
?>