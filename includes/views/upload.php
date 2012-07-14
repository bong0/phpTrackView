<?php render('_header', array('title' => $title))?>
	<?php foreach ($alerts['error'] as $message) : ?>
		<div class="alert alert-error"><?php echo $message?></div>
	<?php endforeach ?> 
	<?php foreach ($alerts['success'] as $message) : ?>
		<div class="alert alert-success"><?php echo $message?></div>
	<?php endforeach ?> 
<div class="row">
	<div class="span12">
		<?php render('_upload'); ?>
	</div>
</div>
<?php render('_footer')?>
