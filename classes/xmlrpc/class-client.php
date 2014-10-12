<?php
namespace Me\Stenberg\Content\Staging\XMLRPC;

use \WP_HTTP_IXR_Client;

class Client {

	private $server;
	private $secret_key;
	private $wp_http_ixr_client;
	private $request;
	private $response;

	public function __construct( $server, $secret_key ) {
		$this->server = $server;
		$this->secret_key = $secret_key;
		$this->wp_http_ixr_client = new WP_HTTP_IXR_Client( trailingslashit( $server ) . 'xmlrpc.php', false, false, CONTENT_STAGING_TRANSFER_TIMEOUT );
	}

	/**
	 * Perform the XML-RPC request and store the response.
	 *
	 * @param string $method
	 * @param array $data
	 * @return array
	 */
	public function query( $method, $data = array() ) {
		$data = $this->encode( serialize( $data ) );

		$args = array(
			$method,
			$this->generate_access_token( $data ),
			$data
		);

		// Disable SSL verification (based on user settings).
		$this->disable_ssl_verification();

		/*
		 * Perform the XML-RPC request. A HTTP status code is returned indicating
		 * whether the request was successful (200) or not (any other code).
		 */
		$status = call_user_func_array( array( $this->wp_http_ixr_client, 'query' ), $args );

		// Enable SSL verification.
		$this->enable_ssl_verification();

		if ( ! $status ) {
			if ( strpos( $this->wp_http_ixr_client->getErrorMessage(), 'requested method smeContentStaging.verify does not exist' ) !== false ) {
				$this->response = array(
					array(
						'level'   => 'error',
						'message' => 'Content Staging plugin not activated on host <strong>' . $this->server . '</strong>',
					)
				);
				return;
			}

			if ( strpos( $this->wp_http_ixr_client->getErrorMessage(), 'Could not resolve host' ) !== false ) {
				$this->response = array(
					array(
						'level'   => 'error',
						'message' => 'Could not connect to host <strong>' . $this->server . '</strong>',
					)
				);
				return;
			}

			$this->response = array(
				array(
					'level'   => 'error',
					'message' => $this->wp_http_ixr_client->getErrorMessage() . ' - on host: ' . $this->server . ' (error code ' . $this->wp_http_ixr_client->getErrorCode() . ')',
				)
			);

		} else {

			// Get the XML-RPC response data.
			$this->response = unserialize( $this->decode( $this->wp_http_ixr_client->getResponse() ) );
		}
	}

	/**
	 * Receive the XML-RPC request, authenticate and return a response.
	 * Response messages is collected from observing objects and returned as
	 * the XML-RPC response data.
	 *
	 * @param array $args
	 * @return array
	 */
	public function handle_request( $args ) {
		$messages = array();

		if ( ! isset( $args[0] ) ) {
			$messages[] = array(
				'level'   => 'error',
				'message' => 'No access token has been provided. Request failed.',
			);
			return $this->prepare_response( $messages );
		}

		if ( ! isset( $args[1] ) ) {
			$messages[] = array(
				'level'   => 'error',
				'message' => 'No data has been provided. Request failed.',
			);
			return $this->prepare_response( $messages );
		}

		$access_token = $args[0];
		$data         = $args[1];

		/*
		 * Check that a valid access token has been provided together with the
		 * request.
		 */
		if ( $access_token !== $this->generate_access_token( $data ) ) {

			// Invalid access token, construct an error message.
			$msg  = 'Authentication failed. ';
			$msg .= '<strong>' . $_SERVER['HTTP_HOST'] . '</strong> did not accept the provided access token. <br/>';
			$msg .= 'Check that your content staging environment and your production environment is using the same secret key.';

			// Respond with error message.
			$messages[] = array(
				'level'   => 'error',
				'message' => $msg,
			);
			return $this->prepare_response( $messages );
		}

		// Get the request data.
		$this->request = unserialize( $this->decode( $data ) );
	}

	/**
	 * Get the request data.
	 *
	 * @return mixed.
	 */
	public function get_request_data() {
		return $this->request;
	}

	/**
	 * Return the response data.
	 */
	public function get_response_data() {
		return $this->response;
	}

	/**
	 * Generate an access token for request validation.
	 *
	 * @param string $data
	 * @return string
	 */
	private function generate_access_token( $data ) {
		return hash_hmac( 'sha1', $data, $this->secret_key );
	}

	/**
	 * Prepare response data.
	 *
	 * @param array $response
	 * @return string
	 */
	public function prepare_response( $response ) {
		return $this->encode( serialize( $response ) );
	}

	/**
	 * @param string $data
	 * @return string
	 */
	private function encode( $data ) {
		$data = gzcompress( $data );
		$data = base64_encode( $data );
		return $data;
	}

	/**
	 * @param string $data
	 * @return string
	 */
	private function decode( $data ) {
		$data = base64_decode( $data );
		$data = gzuncompress( $data );
		return $data;
	}

	/**
	 * Disable SSL verification if we have set DISABLE_SSL_VERIFICATION to
	 * 'true' in our wp-config.php file.
	 */
	private function disable_ssl_verification() {
		if ( defined( 'DISABLE_SSL_VERIFICATION' ) && DISABLE_SSL_VERIFICATION ) {
			add_filter( 'https_local_ssl_verify', '__return_false', 999 );
			add_filter( 'https_ssl_verify', '__return_false', 999 );
		}
	}

	/**
	 * Enable SSL verification.
	 */
	private function enable_ssl_verification() {
		if ( defined( 'DISABLE_SSL_VERIFICATION' ) && DISABLE_SSL_VERIFICATION ) {
			remove_filter( 'https_local_ssl_verify', '__return_false', 999 );
			remove_filter( 'https_ssl_verify', '__return_false', 999 );
		}
	}
}
