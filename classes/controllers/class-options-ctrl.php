<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\View\Template;

class Options_Ctrl {

	/**
	 * @var Template
	 */
	private $template;

	/**
	 * @param Template $template
	 */
	public function __construct( Template $template ) {
		$this->template = $template;
	}

	public function init() {

		// Get options available for sync.
		$options = $this->get_whitelisted_options();

		// Options already selected for syncing.
		$selected_options = get_option( 'sme_wp_options', array() );

		// Prepare options for presentation layer.
		$option_items = $this->prepare_options_for_view( $options, $selected_options );

		$this->template->render(
			'options',
			array( 'options' => $option_items )
		);
	}

	public function save() {

		// Check that the current request carries a valid nonce.
		check_admin_referer( 'sme-save-options', 'sme_save_options_nonce' );

		// Selected options.
		$selected = array();

		if ( isset( $_POST['opt'] ) && is_array( $_POST['opt'] ) ) {
			$selected = $_POST['opt'];
		}

		// Make sure array of selected options only consists of strings.
		$selected = array_filter(
			$selected,
			function( $option ) {
				return is_string( $option );
			}
		);

		// Save selected options.
		update_option( 'sme_wp_options', $selected );

		// Allow third party developers to hook in.
		do_action( 'sme_wp_options_saved', $selected );

		// Handle input data.
		$updated = '';
		if ( isset( $_POST['submit'] ) ) {
			$updated = '&options-updated';
		}

		// Redirect user to this URL when options has been saved.
		$redirect_url = admin_url( 'admin.php?page=sme-wp-options' . $updated );

		// Redirect user.
		wp_redirect( $redirect_url );
		exit();
	}

	/**
	 * Get WordPress options available for syncing.
	 *
	 * @return array
	 */
	private function get_whitelisted_options() {

		// Blacklisted options, should not be possible to sync to production.
		$default_blacklist = $this->get_default_blacklist();

		// Load all options available for this blog.
		$options = wp_load_alloptions();

		// Allow third party developers to modify the blacklist.
		$blacklist = apply_filters( 'sme_wp_options_blacklist', $default_blacklist, $options );

		/*
		 * If blacklist has been improperly altered (e.g. turned into a string)
		 * then re-apply the default blacklist.
		 */
		if ( ! is_array( $blacklist ) ) {
			$blacklist = $default_blacklist;
		}

		// Make sure blacklist only consists of strings.
		$blacklist = array_filter( $blacklist, 'is_string' );

		// Remove blacklisted options from array of options.
		return array_diff_key( $options, array_flip( $blacklist ) );
	}

	/**
	 * Options that should not be possible to sync to production.
	 *
	 * @return array
	 */
	private function get_default_blacklist() {
		return array(
			'siteurl',
			'home',
			'fileupload_url',
			'sme_wp_options',
			'rewrite_rules',
		);
	}

	/**
	 * Options as presented in the view. Includes presentation specific data
	 * such as table row classes, input values etc.
	 *
	 * @todo Consider moving to view layer.
	 *
	 * @param $options
	 * @param $selected_options
	 *
	 * @return array Numeric array were each item consist of an associative
	 * array with the following keys:
	 * (string) key
	 * (string) value
	 * (string) alt
	 * (string) checked
	 */
	private function prepare_options_for_view( $options, $selected_options ) {

		$option_items = array();

		foreach ( $options as $key => $value ) {
			$alt = empty( $alt ) ? 'class="alternate"' : '';
			$option_items[] = array(
				'key'     => $key,
				'value'   => $value,
				'alt'     => $alt,
				'checked' => in_array( $key, $selected_options ) ? 'checked="checked"' : '',
			);
		}

		return $option_items;
	}

}
