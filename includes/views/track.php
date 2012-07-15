<?php render('_header', array('title' => $title)) ?>
<div class="span12">
	<pre><?php print_r(json_decode($data)) ?></pre>
</div>
<?php render('_footer')?>
