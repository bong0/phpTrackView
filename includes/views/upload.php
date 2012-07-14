<?php render('_header', array('title' => $title))?>
<?php if (!empty($alerts['error'])) : ?>
	<?php foreach ($alerts['error'] as $message) : ?>
		<div class="alert alert-error"><?php echo $message?></div>
	<?php endforeach ?> 
<?php endif ?>
<?php if (!empty($alerts['success'])) : ?>
	<?php foreach ($alerts['success'] as $message) : ?>
		<div class="alert alert-success"><?php echo $message?></div>
	<?php endforeach ?> 
<?php endif ?>
<div class="row">
	<div class="span12">
		<?php render('_upload'); ?>
	</div>
</div>
<?php render('_footer')?>
