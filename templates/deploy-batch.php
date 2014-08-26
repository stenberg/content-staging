<div class="wrap">
	<h2>Deploying Batch</h2>

	<?php foreach ( $response as $level => $messages ) { ?>
		<div class="sme-cs-message sme-cs-<?php echo $level; ?>">
			<ul>
				<?php foreach ( $messages as $message ) { ?>
					<li><?php echo $message; ?></li>
				<?php } ?>
			</ul>
		</div>
	<?php } ?>
</div>