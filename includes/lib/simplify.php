<?php

class Vector {
	private $components;
	
	public function __construct(){
		$this->components = array();
	}
	public function addComp($data){
		array_push($components, $data);
	}
	public function getComp($index){
		if($index>0 && $index <= count($this->components)){
			return $this->components[$index];
		}
		return null;
	}
	public function getLength(){
		foreach($this->components as $comp){
			$sum += ppow($comp, 2);
		}
		return sqrt($sum);
	}
	public function getNormalized(){
		$tmpVec = new Vector();
		foreach($this->components as $comp){
			$tmpVec->addComp(pdiv($comp, $this->getLength()));
		}
		return $tmpVec;
	}
}

function simplify($points, $tolerance){
	
	$anchor = 0;
    $floater = count($points) - 1;
    $stack = array();
    $keep = array(); #urpsrüngl. 

	array_push($stack, array($anchor, $floater) );
	while stack:
        anchor, floater = stack.pop()
      
        # initialize line segment
        if pts[floater] != pts[anchor]:
            anchorX = float(pts[floater][0] - pts[anchor][0])
            anchorY = float(pts[floater][1] - pts[anchor][1])
            seg_len = math.sqrt(anchorX ** 2 + anchorY ** 2)
            # get the unit vector
            anchorX /= seg_len
            anchorY /= seg_len
        else:
            anchorX = anchorY = seg_len = 0.0
    

}

?>