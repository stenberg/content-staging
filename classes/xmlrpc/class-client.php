<?php
namespace Me\Stenberg\Content\Staging\XMLRPC;

use Me\Stenberg\Content\Staging\Models\Message;
use \WP_HTTP_IXR_Client;

class Client extends WP_HTTP_IXR_Client {

	private $secret_key;
	private $filtered_request;
	private $filtered_response;

	public function __construct() {

		$endpoint   = 'http://[YOUR_ENDPOINT_HERE]';
		$secret_key = 'YOUR_SECRET_KEY';

		if ( defined( 'CONTENT_STAGING_ENDPOINT' ) && CONTENT_STAGING_ENDPOINT ) {
			$endpoint = CONTENT_STAGING_ENDPOINT;
		} else if ( $endpoint_opt = get_option( 'sme_cs_endpoint' ) ) {
			$endpoint = $endpoint_opt;
		}

		// Set secret key.
		if ( defined( 'CONTENT_STAGING_SECRET_KEY' ) && CONTENT_STAGING_SECRET_KEY ) {
			$secret_key = CONTENT_STAGING_SECRET_KEY;
		} else if ( $secret_key_opt = get_option( 'sme_cs_secret_key' ) ) {
			$secret_key = $secret_key_opt;
		}

		// Allow filtering of endpoint and secret key.
		$endpoint   = apply_filters( 'sme_endpoint', $endpoint );
		$secret_key = apply_filters( 'sme_secret_key', $secret_key );

		$this->secret_key = $secret_key;

		parent::__construct( trailingslashit( $endpoint ) . 'xmlrpc.php', false, false, CONTENT_STAGING_TRANSFER_TIMEOUT );
	}

	/**
	 * Perform the XML-RPC request and store the response.
	 *
	 * @param string $method
	 * @param array $data
	 * @return array
	 */
	public function request( $method, $data = array() ) {
		$data = $this->encode( serialize( $data ) );

		$args = array(
			$method,
			$this->generate_access_token( $data ),
			$data,
		);

		// Allow custom path to send XML-RPC request to.
		$this->path = apply_filters( 'sme_xmlrpc_path', $this->path );

		// Allow custom headers.
		$this->headers = apply_filters( 'sme_client_headers', array() );

		// Disable SSL verification (based on user settings).
		$this->disable_ssl_verification();

		/*
		 * Perform the XML-RPC request. A HTTP status code is returned indicating
		 * whether the request was successful (200) or not (any other code).
		 */
		$status = call_user_func_array( array( $this, 'query' ), $args );

		// Enable SSL verification.
		$this->enable_ssl_verification();

		if ( ! $status ) {

			if ( strpos( $this->getErrorMessage(), 'requested method smeContentStaging.verify does not exist' ) !== false ) {
				$message = new Message();
				$message->set_level( 'error' );
				$message->set_message(
					sprintf( 'Content Staging plugin not activated on host <strong>%s</strong>', $this->server )
				);

				$this->filtered_response = array(
					'status'   => 2,
					'messages' => array( $message ),
				);

				return;
			}

			if ( strpos( $this->getErrorMessage(), 'Could not resolve host' ) !== false ) {
				$message = new Message();
				$message->set_level( 'error' );
				$message->set_message(
					sprintf( 'Could not connect to host <strong>%s</strong>', $this->server )
				);

				$this->filtered_response = array(
					'status'   => 2,
					'messages' => array( $message ),
				);

				return;
			}

			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message(
				sprintf(
					'%s - on host: %s (error code %s)',
					$this->getErrorMessage(),
					$this->server,
					$this->getErrorCode()
				)
			);

			$this->filtered_response = array(
				'status'   => 2,
				'messages' => array( $message ),
			);

		} else {

			// Get the XML-RPC response data.
			$this->filtered_response = unserialize( $this->decode( $this->getResponse() ) );
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

		if ( ! isset( $args[0] ) ) {
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( 'No access token has been provided. Request failed.' );

			$response = array(
				'status'   => 2,
				'messages' => array( $message ),
			);

			return $this->prepare_response( $response );
		}

		if ( ! isset( $args[1] ) ) {
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( 'No data has been provided. Request failed.' );

			$response = array(
				'status'   => 2,
				'messages' => array( $message ),
			);

			return $this->prepare_response( $response );
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
			$msg .= sprintf( '<strong>%s</strong> did not accept the provided access token. <br/>', $_SERVER['HTTP_HOST'] );
			$msg .= 'Check that your content staging environment and your production environment is using the same secret key.';

			// Respond with error message.
			$message = new Message();
			$message->set_level( 'error' );
			$message->set_message( $msg );

			$response = array(
				'status'   => 2,
				'messages' => array( $message ),
			);

			return $this->prepare_response( $response );
		}

		// Get the request data.
		$this->filtered_request = unserialize( $this->decode( $data ) );
	}

	/**
	 * Get the request data.
	 *
	 * @return mixed.
	 */
	public function get_request_data() {
		return $this->filtered_request;
	}

	/**
	 * Return the response data.
	 */
	public function get_response_data() {
		return $this->filtered_response;
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
	 *
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
