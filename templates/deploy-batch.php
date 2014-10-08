<div class="wrap">
	<h2>Deploying Batch</h2>

	<div class="sme-deploy-messages">
		<?php foreach ( $messages as $message ) { ?>
			<div class="sme-cs-message sme-cs-<?php echo $message['level']; ?>">
				<ul>
					<li><?php echo $message['message']; ?></li>
				</ul>
			</div>
		<?php } ?>
	</div>
	<div id="sme-importing" class="sme-cs-message sme-cs-info">
		<p><div class="sme-loader-gif"></div> Importing...</p>
	</div>
</div>