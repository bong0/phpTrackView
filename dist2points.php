<?php
include_once('./common.php');

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

?>
