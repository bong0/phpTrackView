<?php

define('BCMATH_PRECISION', 50);

function getDistance($latitude1, $longitude1, $latitude2, $longitude2) {  
    $earth_radius = 6371.8; # FIXME not precise 
      
    $dLat = deg2rad(bcsub($latitude2, $latitude1, BCMATH_PRECISION));
    $dLon = deg2rad(bcsub($longitude2, $longitude1, BCMATH_PRECISION));

	$c1 = ppow(sin(pdiv($dLat,2)), 2);
	$c2 = cos(deg2rad($latitude1));
	$c3 = cos(deg2rad($latitude2));
	$c4 = ppow(sin(pdiv($dLon,2)), 2);

	$a = padd( $c1 , pmul( pmul($c2 , $c3) , $c4 ) );
    $c = pmul(2 , asin(sqrt($a)));
    $d = pmul($earth_radius, $c);

    return $d;
 }

#wrapper functions for bcmath operations; appending BCMATH_PRECISION to operand-list
function ppow($op1, $op2){
  #return pow($op1,$op2);
  return bcpow(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function pmul($op1, $op2){
  #return ($op1*$op2);
  return bcmul(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function padd($op1,$op2){
  #return $op1+$op2;
  return bcadd(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function pdiv($op1,$op2){
	#return $op1/$op2;
  
		$op1 = xpnd($op1);
		$op2 = xpnd($op2);
	/*  echo $op1."\n";
	  echo $op2."\n";*/
  return bcdiv($op1,$op2,BCMATH_PRECISION);
}

# convert output of used php-math functions like sin in scientific notation to decimal notation
function xpnd($scientific){ # expand from scientific notation
  if(is_int($scientific)){ #don't convert integers
	return $scientific; 
  }
  return sprintf("%.".BCMATH_PRECISION."F", $scientific);
}
?>
