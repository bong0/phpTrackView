<?php render('_header', array('title' => $title))?>
<div class="row">
	<div class="span5">
		<h2>Track upload</h2>
		<?php render('_upload'); ?>
	</div>
	<div class="span7">
		<div class="hero-unit">
			<h1>phpTrackView</h1>
			<p>Tagline</p>
			<p>
				<a class="btn btn-primary btn-large">Learn more</a>
			</p>
		</div>
	</div>
	<?php render($tracks); ?>
</div>
<?php render('_footer')?>
