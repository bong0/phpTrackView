<?php

header('HTTP/1.0 404 Not Found');
render('_header', array('title' => 'Oops!'));

?>

<div class="alert alert-error">
	<?php echo $message?>
</div>

<?php render('_footer')?>
