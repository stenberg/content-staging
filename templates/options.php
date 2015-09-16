<?php
/**
 * @var array
 */
$options;
?>
<div class="wrap">
	<h2><?php _e( 'WordPress Options', 'sme-content-staging' ); ?></h2>
	<p>
		<?php _e( 'WordPress options you wish to sync to your Production environment.', 'sme-content-staging' ); ?>
	</p>

	<?php if ( isset( $_REQUEST['options-updated'] ) ) { ?>
		<div id="message" class="updated">
			<p>
				<?php _e( 'WordPress options you wish to sync has been updated.', 'sme-content-staging' ); ?>
			</p>
		</div>
	<?php } ?>

	<form method="post" action="<?php echo admin_url( 'admin-post.php?action=sme-save-options' ); ?>">

		<?php wp_nonce_field( 'sme-save-options','sme_save_options_nonce' ); ?>

		<table class="widefat fixed">
			<thead>
				<tr>
					<th class="check-column"></th>
					<th><?php _e( 'Option' ); ?></th>
					<th><?php _e( 'Current Value' ); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ( $options as $opt ) { ?>
				<tr <?php echo $opt['alt']; ?>>
					<td class="column-cb">
						<input type="checkbox" id="<?php echo $opt['key']; ?>" name="opt[]" value="<?php echo $opt['key']; ?>" <?php echo $opt['checked']; ?>>
					</td>
					<td><label for="<?php echo $opt['key']; ?>"><?php echo $opt['key']; ?></label></td>
					<td><?php echo $opt['value']; ?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>

		<?php do_action( 'sme_wp_options_pre_buttons', $options ); ?>

		<?php submit_button(); ?>
	</form>
</div>