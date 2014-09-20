<div class="wrap">
	<h2>Deploying Batch</h2>

	<?php foreach ( $messages as $message ) { ?>
		<div class="sme-cs-message sme-cs-<?php echo $message['level']; ?>">
			<ul>
				<li><?php echo $message['message']; ?></li>
			</ul>
		</div>
	<?php } ?>
</div>