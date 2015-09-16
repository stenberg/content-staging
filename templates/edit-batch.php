<div class="wrap">

	<!-- Enable JavaScript to pick up the batch ID. -->
	<span id="sme-batch-id" class="hidden"><?php echo $batch->get_id();?></span>

	<h2><?php echo $label; ?></h2>
	<p><?php _e( 'Select posts you would like to include in your content batch.', 'sme-content-staging' ); ?></p>

	<?php if ( isset( $_GET['updated'] ) ) { ?>
		<div class="updated">
			<p><?php _e( 'Content batch has been updated!', 'sme-content-staging' ); ?></p>
		</div>
	<?php } ?>

	<form method="post" action="<?php echo admin_url( 'admin-post.php?action=sme-save-batch&id=' . $batch->get_id() ); ?>">

		<?php wp_nonce_field( 'sme-save-batch','sme_save_batch_nonce' ); ?>
		<input type="hidden" name="post_ids" value="<?php echo $post_ids; ?>">

		<input type="text" name="batch_title" size="30" value="<?php echo $batch->get_title(); ?>" class="sme-input-text" placeholder="Enter batch title here" autocomplete="off">

		<?php echo $filters; ?>
		<?php $table->display(); ?>

		<h2><?php echo $wp_options['title']; ?></h2>
		<p>
			<input type="checkbox" name="include_wp_options" id="include_wp_options" <?php echo $wp_options['checked']; ?>>
			<label for="include_wp_options">
				<?php echo $wp_options['description']; ?>
			</label>
			<br><br>
		</p>

		<?php do_action( 'sme_view_edit_batch_pre_buttons', $batch ); ?>

		<?php submit_button( 'Save Batch', 'primary', 'submit', false ); ?>
		<?php submit_button( 'Pre-Flight Batch', 'secondary', 'submit', false ); ?>
		<input type="button" name="button" id="button" class="button" onclick="location.href='<?php echo admin_url( 'admin.php?page=sme-list-batches' ); ?>'" value="Cancel">
	</form>

</div>