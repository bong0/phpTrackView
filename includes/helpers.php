<?php

/* These are helper functions */

function render($template, $vars = array()) {
	
	// This function takes the name of a template and
	// a list of variables, and renders it.
	
	// This will create variables from the array:
	extract($vars);
	
	// It can also take an array of objects
	// instead of a template name.
	if (is_array($template)) {
		
		// If an array was passed, it will loop
		// through it, and include a partial view
		foreach ($template as $o) {
			
			// This will create a local variable
			// with the name of the object's class
			$c = strtolower(get_class($o));
			$$c = $o;
			
			include "views/_$c.php";
		}
		return;
	}
	include "views/$template.php";
}

// Helper function for title formatting:
function formatTitle($title = '') {
	if ($title) $title .= ' | ';
	$title .= $GLOBALS['defaultTitle'];
	return $title;
}

function esc_html($val) {
	return htmlentities($val, ENT_NOQUOTES, 'UTF-8');
}

function __($str) {
	return $str;
}
