<?php

/*
 * This functions are mostly taken from WordPress 3.4.1
 */


/*
 * Sanitizes a filename replacing whitespace with dashes
 */
function sanitize_file_name($filename) {
	$special_chars = array('?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', '\'', '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', chr(0));
	$filename = str_replace($special_chars, '', $filename);
	$filename = preg_replace('/[\s-]+/', '-', $filename);
	$filename = trim($filename, '.-_');

	// Split the filename into a base and extension[s]
	$parts = explode('.', $filename);

	// Return if only one extension
	if (count($parts) <= 2) return $filename;

	// Process multiple extensions
	$filename = array_shift($parts);
	$extension = array_pop($parts);
	$mimes = get_allowed_mime_types();

	// Loop over any intermediate extensions. Munge them with a trailing underscore if they are a 2 - 5 character
	// long alpha string not in the extension whitelist.
	// Read http://h-online.com/-859597 if you want to know, why we need to do this
	foreach ((array) $parts as $part) {
		$filename .= '.' . $part;

		if (preg_match('/^[a-zA-Z]{2,5}\d?$/', $part)) {
			$allowed = false;
			foreach ($mimes as $ext_preg => $mime_match) {
				$ext_preg = '!^(' . $ext_preg . ')$!i';
				if (preg_match($ext_preg, $part)) {
					$allowed = true;
					break;
				}
			}
			if (!$allowed) $filename .= '_';
		}
	}
	$filename .= '.' . $extension;
	return $filename;
}


/*
 * Get a filename that is sanitized and unique for the given directory.
 */
function unique_filename($dir, $filename) {
	// sanitize the file name before we begin processing
	$filename = sanitize_file_name($filename);

	// separate the filename into a name and extension
	$info		= pathinfo($filename);
	$ext		= !empty($info['extension']) ? '.' . strtolower($info['extension']) : '';
	$filename	= basename($filename, $ext);

	// Increment the file number until we have a unique file to save in $dir.
	$number = '';
	$current = $filename . $ext;
	while (file_exists($dir . "/$current")) {
		$current = $filename . ++$number . $ext;
	}
	return $current;
}


/*
 * Retrieve the file type from the file name.
 */
function check_filetype($filename, $mimes = null) {

	if (empty($mimes)) $mimes = get_allowed_mime_types();

	$type = false;
	$ext = false;

	foreach ($mimes as $ext_preg => $mime_match) {
		$ext_preg = '!\.(' . $ext_preg . ')$!i';
		if (preg_match( $ext_preg, $filename, $ext_matches)) {
			$type = $mime_match;
			$ext = $ext_matches[1];
			break;
		}
	}

	return compact('ext', 'type');
}

/*
 * Retrieve list of allowed mime types and file extensions.
 */
function get_allowed_mime_types() {
	return array(
		'gpx' => 'application/octet-stream',
		'bz2' => 'application/x-bzip',
		// TODO tcx
		// TODO kml
	);
}
