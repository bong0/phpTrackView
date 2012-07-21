<?php render('_header', array('title' => $title)) ?>
<div class="span12">
	<pre>	<?php	print_r(json_decode($data));	?>
	</pre>
	<pre>	<?php	$simplifier = new PathSimplifier();
			$simplifiedGPX = $simplifier->simplifyMultiple(json_decode($data, true), 0.00005);
			print_r($simplifiedGPX);	?>
	</pre>
</div>
<?php render('_footer')?>
