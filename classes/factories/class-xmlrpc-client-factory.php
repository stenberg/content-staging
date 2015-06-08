<?php
namespace Me\Stenberg\Content\Staging\Factories;

use Me\Stenberg\Content\Staging\XMLRPC\Client;
use WP_HTTP_IXR_Client;

class XMLRPC_Client_Factory {

	/**
	 * Create XML-RPC client object.
	 *
	 * @return Client
	 */
	public function create() {

		$endpoint   = $this->get_endpoint();
		$secret_key = $this->get_secret_key();
		$timeout    = $this->get_transfer_timeout();

		// XMLRPC client.
		$ixr_client = new WP_HTTP_IXR_Client( $endpoint, false, false, $timeout );

		 return new Client( $ixr_client, $secret_key );
	}

	/**
	 * Get endpoint.
	 *
	 * @return string
	 */
	private function get_endpoint() {

		$endpoint = 'http://[YOUR_ENDPOINT_HERE]';

		if ( defined( 'CONTENT_STAGING_ENDPOINT' ) && CONTENT_STAGING_ENDPOINT ) {
			$endpoint = CONTENT_STAGING_ENDPOINT;
		} else if ( $endpoint_opt = get_option( 'sme_cs_endpoint' ) ) {
			$endpoint = $endpoint_opt;
		}

		// Allow filtering of endpoint.
		$endpoint = apply_filters( 'sme_endpoint', $endpoint );
		return trailingslashit( $endpoint ) . 'xmlrpc.php';
	}

	/**
	 * Get secret key.
	 *
	 * @return string
	 */
	private function get_secret_key() {

		$secret_key = 'YOUR_SECRET_KEY';

		// Set secret key.
		if ( defined( 'CONTENT_STAGING_SECRET_KEY' ) && CONTENT_STAGING_SECRET_KEY ) {
			$secret_key = CONTENT_STAGING_SECRET_KEY;
		} else if ( $secret_key_opt = get_option( 'sme_cs_secret_key' ) ) {
			$secret_key = $secret_key_opt;
		}

		// Allow filtering of endpoint and secret key.
		return apply_filters( 'sme_secret_key', $secret_key );
	}

	/**
	 * Get transfer timeout.
	 *
	 * @return int
	 */
	private function get_transfer_timeout() {

		$timeout = 60;

		if ( defined( 'CONTENT_STAGING_TRANSFER_TIMEOUT' ) ) {
			$timeout = CONTENT_STAGING_TRANSFER_TIMEOUT;
		}

		return $timeout;
	}

}