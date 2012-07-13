#!/usr/bin/php
<?php

include_once("parser.php");
$DEBUG = 0;

$file="testfiles/Altkönigteile.gpx"; # TODO: only for testing

$parser = new GpxParser();
$parser->setInput($file);
$parser->setDebug();
#echo "parsing file...\n";
$parser->parse();
#echo "done.\n";
print($parser->getResult());

?>