<div class="wrap">

	<?php if ( isset( $_GET['updated'] ) ) { ?>
		<div class="updated">
			<p><?php _e( 'Content batch has been updated!', 'sme-content-staging' ); ?></p>
		</div>
	<?php } ?>

	<form method="post" action="<?php echo admin_url( 'admin-post.php?action=sme-save-batch&id=' . $batch->get_id() ); ?>">

		<?php wp_nonce_field( 'sme-save-batch','sme_save_batch_nonce' ); ?>

		<input type="text" name="batch_title" size="30" value="<?php echo $batch->get_title(); ?>" class="sme-input-text" placeholder="Batch Title" autocomplete="off">
		<?php $table->display(); ?>

		<?php submit_button( 'Save Batch', 'primary', 'submit', false ); ?>
		<?php submit_button( 'Pre-Flight Batch', 'secondary', 'submit', false ); ?>
		<input type="button" name="button" id="button" class="button" onclick="location.href='<?php echo admin_url( 'admin.php?page=sme-list-batches' ); ?>'" value="Cancel">
	</form>

</div>