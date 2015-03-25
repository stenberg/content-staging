<div class="wrap">
    <h2><?php _e( 'Content Staging Settings' ); ?></h2>

    <?php
    if ( isset( $_REQUEST["settings-updated"] ) ) {
        echo '<div id="message" class="updated"><p>Settings have been updated.</p></div>';
    }
    ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'content-staging-settings' ); ?>
        <?php do_settings_sections( 'content-staging-settings' ); ?>
        <table class="form-table">
            <tr valign="top">
            <th scope="row">Remote URL:</th>
            <td><input type="text" name="remote_site_url" size="20" value="<?php echo esc_attr( get_option('remote_site_url') ); ?>" /></td>
            </tr>
            <tr valign="top">
            <th scope="row">Remote Secret Key:</th>
            <td><input type="text" name="remote_site_secret_key" size="20" value="<?php echo esc_attr( get_option('remote_site_secret_key') ); ?>" /> <button onclick="event.preventDefault(); app.generateKey('remote_site_secret_key');">Generate Key</button><p><small>This must be the same on both environments.</small></td>
            </tr>
        </table>
        
        <?php submit_button(); ?>

    </form>
</div>