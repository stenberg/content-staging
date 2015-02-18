<div class="wrap">
	<h2>Pre-Flight</h2>

	<div class="sme-deploy-messages">
		<?php foreach ( $messages as $message ) { ?>
			<div class="sme-cs-message sme-cs-<?php echo $message['level']; ?>">
				<p><?php echo $message['message']; ?></p>
			</div>
		<?php } ?>
	</div>

	<div id="sme-importing" class="sme-cs-message sme-cs-info">
		<p><div class="sme-loader-gif"></div> Performing pre-flight checks...</p>
	</div>

	<form method="post" action="<?php echo admin_url( 'admin.php?page=sme-send-batch&id=' . $batch->get_id() ); ?>">
		<?php wp_nonce_field( 'sme-deploy-batch','sme_deploy_batch_nonce' ); ?>
		<?php submit_button( 'Deploy Batch', 'primary', 'submit', false, array( 'disabled' => 'disabled' ) ); ?>
		<input type="button" name="button" id="button" class="button" onclick="location.href='<?php echo admin_url( 'admin.php?page=sme-edit-batch&id=' . $batch->get_id() ); ?>'" value="Cancel">
	</form>
</div>