<div class="wrap">

	<div class="error">
		<p><?php echo sprintf( __( 'Are you sure you want to delete batch %s?', 'sme-content-staging' ), '<strong>' . $batch->get_title() . '</strong>' ); ?></p>
	</div>

	<form method="post" action="<?php echo admin_url( 'admin-post.php?action=sme-delete-batch&id=' . $batch->get_id() ); ?>">
		<?php wp_nonce_field( 'sme-delete-batch','sme_delete_batch_nonce' ); ?>
		<input type="hidden" name="delete" value="delete">
		<?php submit_button( 'Delete Batch', 'primary', 'submit', false ); ?>
		<input type="button" name="button" id="button" class="button" onclick="location.href='<?php echo admin_url( 'admin.php?page=sme-list-batches' ); ?>'" value="Cancel">
	</form>

</div>