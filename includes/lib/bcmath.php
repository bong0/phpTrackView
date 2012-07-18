<?php

define('BCMATH_PRECISION', 20);

#wrapper functions for bcmath operations; appending BCMATH_PRECISION to operand-list
function ppow($op1, $op2){
  return bcpow(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function pmul($op1, $op2){
  return bcmul(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function padd($op1,$op2){
  return bcadd(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function psub($op1,$op2){
  return bcsub(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}
function pdiv($op1,$op2){
  return bcdiv(xpnd($op1),xpnd($op2),BCMATH_PRECISION);
}

# convert output of used php-math functions like sin in scientific notation to decimal notation
function xpnd($scientific){ # expand from scientific notation
  if(is_int($scientific)){ #don't convert integers
	return $scientific; 
  }
  return sprintf("%.".BCMATH_PRECISION."F", $scientific);
}

?>
