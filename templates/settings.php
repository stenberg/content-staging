<?php
/**
 * @var string $endpoint
 * @var string $secret_key
 */
?>
<div class="wrap">
	<h2><?php _e( 'Content Staging Settings' ); ?></h2>

	<?php if ( isset( $_REQUEST['settings-updated'] ) ) { ?>
		<div id="message" class="updated"><p>Settings have been updated.</p></div>
	<?php } ?>

	<form method="post" action="options.php">

		<?php settings_fields( 'content-staging-settings' ); ?>
		<?php do_settings_sections( 'content-staging-settings' ); ?>

		<table class="form-table">
			<tr valign="top">
				<th scope="row">Remote URL:</th>
				<td><input type="text" name="sme_cs_endpoint" size="60" value="<?php echo $endpoint; ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Remote Secret Key:</th>
				<td><input type="text" id="sme-secret-key" name="sme_cs_secret_key" size="60" value="<?php echo $secret_key; ?>" /> <button id="sme-generate-key">Generate Key</button><p><small>This must be the same on both environments.</small></td>
			</tr>
		</table>

		<?php submit_button(); ?>

	</form>
</div>