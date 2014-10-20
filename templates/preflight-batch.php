<?php
// @todo To much logic here! Create view classes to handle this.

$submit_btn_attr = array( 'id' => 'sme-cs-deploy-batch-btn' );

/*
 * If an error message exists in the response, then deactivate the
 * Deploy Batch button.
 */
if ( ! $is_success ) {
	$submit_btn_attr['disabled'] = 'disabled';
}
?>

<div class="wrap">
	<h2>Pre-Flight</h2>

	<?php foreach ( $messages as $message ) { ?>
		<div class="sme-cs-message sme-cs-<?php echo $message['level']; ?>">
			<p><?php echo $message['message']; ?></p>
		</div>
	<?php } ?>

	<form method="post" action="<?php echo admin_url( 'admin.php?page=sme-send-batch&id=' . $batch->get_id() ); ?>">
		<?php wp_nonce_field( 'sme-deploy-batch','sme_deploy_batch_nonce' ); ?>
		<?php submit_button( 'Deploy Batch', 'primary', 'submit', false, $submit_btn_attr ); ?>
		<input type="button" name="button" id="button" class="button" onclick="location.href='<?php echo admin_url( 'admin.php?page=sme-edit-batch&id=' . $batch->get_id() ); ?>'" value="Cancel">
	</form>
</div>