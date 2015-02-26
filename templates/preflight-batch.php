<div class="wrap">
	<h2>Pre-Flight</h2>

	<?php if ( $status < 2 ) { ?>
	<span id="sme-batch-id" class="hidden"><?php echo $batch->get_id(); ?></span>
	<span id="sme-batch-guid" class="hidden"><?php echo $batch->get_guid(); ?></span>
	<?php } ?>

	<div class="sme-deploy-messages">
		<?php foreach ( $messages as $message ) { ?>
			<div class="sme-cs-message sme-cs-<?php echo $message->get_level(); ?>">
				<p><?php echo $message->get_message(); ?></p>
			</div>
		<?php } ?>
	</div>

	<?php if ( $status < 2 ) { ?>
	<div id="sme-importing" class="sme-cs-message sme-cs-info">
		<p><div class="sme-loader-gif"></div> Performing pre-flight checks...</p>
	</div>
	<?php } ?>

	<form method="post" action="<?php echo admin_url( 'admin.php?page=sme-send-batch&id=' . $batch->get_id() ); ?>">
		<?php wp_nonce_field( 'sme-deploy-batch','sme_deploy_batch_nonce' ); ?>
		<?php submit_button( 'Deploy Batch', 'primary', 'submit', false, $deploy_btn ); ?>
		<input type="button" name="button" id="button" class="button" onclick="location.href='<?php echo admin_url( 'admin.php?page=sme-edit-batch&id=' . $batch->get_id() ); ?>'" value="Cancel">
	</form>
</div>