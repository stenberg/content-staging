<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\View\Template;

class Options_Ctrl {

	/**
	 * @var Template
	 */
	private $template;

	/**
	 * Default whitelist.
	 *
	 * Options that by default should be possible to sync to production.
	 * Only used if a whitelist strategy is used (as opposed to a blacklist
	 * strategy).
	 *
	 * @var array
	 */
	private $whitelist = array(
		'blogname',
		'admin_email',
		'blogdescription',
		'start_of_week',
		'use_smilies',
	);

	/**
	 * Default blacklist.
	 *
	 * Options that by default should not be possible to sync to production.
	 * Only used if a blacklist strategy is used (as opposed to a whitelist
	 * strategy).
	 *
	 * @var array
	 */
	private $blacklist = array(
			'siteurl',
			'home',
			'fileupload_url',
			'sme_wp_options',
			'rewrite_rules',
	);

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

		// Use whitelist or blacklist.
		$use_whitelist = apply_filters( 'sme_wp_options_use_whitelist', false );

		// Load all options available for this blog.
		$options = wp_load_alloptions();

		if ( $use_whitelist ) {
			return $this->get_whitelist( $options );
		}

		return $this->get_blacklist( $options );
	}

	/**
	 * Get all options that are whitelisted.
	 *
	 * @param array $options All available options.
	 * @return array
	 */
	private function get_whitelist( $options ) {

		// Allow third party developers to modify the whitelist.
		$whitelist = apply_filters( 'sme_wp_options_whitelist', $this->whitelist, $options );

		/*
		 * If whitelist has been improperly altered (e.g. turned into a string)
		 * then re-apply the default whitelist.
		 */
		if ( ! is_array( $whitelist ) ) {
			$whitelist = $this->whitelist;
		}

		// Make sure whitelist only consists of strings.
		$whitelist = array_filter( $whitelist, 'is_string' );

		// Remove all options that are not whitelisted.
		return array_intersect_key( $options, array_flip( $whitelist ) );
	}

	/**
	 * Get all options that are not blacklisted.
	 *
	 * @param array $options All available options.
	 * @return array
	 */
	private function get_blacklist( $options ) {

		// Allow third party developers to modify the blacklist.
		$blacklist = apply_filters( 'sme_wp_options_blacklist', $this->blacklist, $options );

		/*
		 * If blacklist has been improperly altered (e.g. turned into a string)
		 * then re-apply the default blacklist.
		 */
		if ( ! is_array( $blacklist ) ) {
			$blacklist = $this->blacklist;
		}

		// Make sure blacklist only consists of strings.
		$blacklist = array_filter( $blacklist, 'is_string' );

		// Remove blacklisted options from array of options.
		return array_diff_key( $options, array_flip( $blacklist ) );
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
				'value'   => htmlspecialchars($value),
				'alt'     => $alt,
				'checked' => in_array( $key, $selected_options ) ? 'checked="checked"' : '',
			);
		}

		return $option_items;
	}

}
