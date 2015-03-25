<div class="wrap">
	<h2>Deploying Batch</h2>

	<div class="sme-deploy-messages">
		<?php foreach ( $messages as $message ) { ?>
			<div class="sme-cs-message sme-cs-<?php echo $message->get_level(); ?>">
				<ul>
					<li><?php echo $message->get_message(); ?></li>
				</ul>
			</div>
		<?php } ?>
	</div>
	<div id="sme-importing" class="sme-cs-message sme-cs-info">
		<p><i class="fa fa-fw fa-refresh fa-spin"></i></div> Importing...</p>
	</div>
</div>