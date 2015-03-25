<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\View\Template;

class Settings_Ctrl {

    /**
     * @var Template
     */
    private $template;

    /**
     * @param Template $template
     */
    public function __construct( Template $template ) {
        $this->template  = $template;
        if ( is_admin() ) {
            add_action( 'admin_init', array( $this, 'register_settings' ) );
        }
    }

    public function init() {
        $this->template->render( 'settings', array() );
    }

    public function register_settings()
    {
        register_setting( 'content-staging-settings', 'remote_site_url' );
        register_setting( 'content-staging-settings', 'remote_site_secret_key' );
        register_setting( 'content-staging-settings', 'current_site_secret_key' );
    }

    public function generate_key()
    {
        $private_key = bin2hex( openssl_random_pseudo_bytes( 40 ) );
        
        header( 'Content-Type: application/json' );
        echo json_encode( array( 'key' => $private_key ) );

        die(); // Required to return a proper result.
    }

}
