<?php
namespace Me\Stenberg\Content\Staging\XMLRPC;

use Me\Stenberg\Content\Staging\Models\Message;
use \WP_HTTP_IXR_Client;

class Client {

	/**
	 * @var WP_HTTP_IXR_Client
	 */
	private $ixr_client;

	/**
	 * Arguments to send with the XML-RPC request.
	 *
	 * @var array
	 */
	private $request_args;

	/**
	 * Number of request attempts.
	 *
	 * @var int
	 */
	private $attempts;

	private $secret_key;
	private $filtered_request;
	private $filtered_response;

	/**
	 * Constructor.
	 */
	public function __construct( WP_HTTP_IXR_Client $ixr_client, $secret_key ) {
		$this->ixr_client   = $ixr_client;
		$this->secret_key   = $secret_key;
		$this->request_args = array();
		$this->attempts     = 0;
	}

	/************************************************************************
	 * Perform Request.
	 *
	 * Operations carried out on Content Stage to handle sending a XML-RPC
	 * request to Production.
	 ************************************************************************/

	/**
	 * Prepare and perform the XML-RPC request and return the response.
	 *
	 * @param string $method
	 * @param array  $data
	 *
	 * @return array
	 */
	public function request( $method, $data = array() ) {

		$this->request_args        = $this->prepare_request_args( $method, $data );
		$this->ixr_client->path    = $this->prepare_request_path( $this->ixr_client->path );
		$this->ixr_client->headers = $this->prepare_request_headers( $this->ixr_client->headers );

		// Send request.
		$query_successful = $this->send();

		if ( ! $query_successful ) {
			return $this->get_error_message();
		}

		// Get the XML-RPC response data.
		return unserialize( $this->decode( $this->filtered_response ) );
	}

	/**
	 * Perform XML-RPC request.
	 *
	 * @return bool
	 */
	private function send() {

		// Disable SSL verification (based on user settings).
		$this->disable_ssl_verification();

		// Perform XML-RPC request. Returns true on success, false on failure.
		$query_successful = call_user_func_array( array( $this->ixr_client, 'query' ), $this->request_args );

		// Enable SSL verification.
		$this->enable_ssl_verification();

		// On failure, increment number of request and retry.
		if ( ! $query_successful ) {
			$this->attempts++;
			return $this->retry();
		}

		// Reset number of request attempts.
		$this->attempts = 0;

		// Get response.
		$this->filtered_response = $this->ixr_client->getResponse();

		return $query_successful;
	}

	/**
	 * Retry sending the XML-RPC request.
	 *
	 * @return bool True on successful request, false on failure.
	 */
	private function retry() {

		// Error message.
		$msg = $this->ixr_client->getErrorMessage();

		// Log.
		error_log(
			sprintf(
				'[SME] Request to host %s failed: %s (error code %s)',
				$this->ixr_client->server, $msg, $this->ixr_client->getErrorCode()
			)
		);

		// Error messages that should trigger request to be re-sent.
		$retry_triggers = array(
			'transport error - HTTP status code was not 200 (500)'
		);

		// Anonymous function checking if a phrase from an error message is
		// part of the actual error message.
		$retry_callback = function( $carry, $item ) use ( $msg ) {
			if ( $carry ) return $carry;
			return strpos( $msg, $item ) !== false;
		};

		// Check if request should be re-sent.
		$should_retry = array_reduce( $retry_triggers, $retry_callback, false );

		// Request should not be re-sent, return error message.
		if ( $this->attempts >= 3 || ! $should_retry ) {
			$this->attempts = 0;
			return false;
		}

		// Wait time until next request.
		$seconds = 5 * $this->attempts;

		// Log.
		error_log( sprintf( '[SME] Re-send request in %d seconds...', $seconds ) );

		// Wait before trying to send the request again.
		sleep( $seconds );

		return $this->send();
	}

	/**
	 * Arguments to be sent with the XML-RPC request.
	 *
	 * @param string $method
	 * @param array  $data
	 *
	 * @return array
	 */
	private function prepare_request_args( $method, $data = array() ) {

		$data = $this->encode( serialize( $data ) );

		return array(
			$method,
			$this->generate_access_token( $data ),
			$data,
		);
	}

	/**
	 * Set custom path to send XML-RPC request to.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function prepare_request_path( $path ) {
		return apply_filters( 'sme_xmlrpc_path', $path );
	}

	/**
	 * Set custom request headers.
	 *
	 * @param array $headers
	 *
	 * @return array
	 */
	private function prepare_request_headers( $headers ) {
		return apply_filters( 'sme_client_headers', $headers );
	}

	/**
	 * Handle failed request.
	 *
	 * @return array
	 */
	private function get_error_message() {

		if ( strpos( $this->ixr_client->getErrorMessage(), 'requested method smeContentStaging.verify does not exist' ) !== false ) {
			return $this->error_plugin_inactive();
		}

		if ( strpos( $this->ixr_client->getErrorMessage(), 'Could not resolve host' ) !== false ) {
			return $this->error_host_not_found();
		}

		return $this->error_general();
	}

	/**
	 * Content Staging plugin is not active.
	 *
	 * @return array
	 */
	private function error_plugin_inactive() {
		$message = new Message();
		$message->set_level( 'error' );
		$message->set_message(
			sprintf( 'Content Staging plugin not activated on host <strong>%s</strong>', $this->ixr_client->server )
		);

		return array(
			'status'   => 2,
			'messages' => array( $message ),
		);
	}

	/**
	 * Remote host could not be found.
	 *
	 * @return array
	 */
	private function error_host_not_found() {
		$message = new Message();
		$message->set_level( 'error' );
		$message->set_message(
			sprintf( 'Could not connect to host <strong>%s</strong>', $this->ixr_client->server )
		);

		return array(
			'status'   => 2,
			'messages' => array( $message ),
		);
	}

	/**
	 * Error occurred during request.
	 *
	 * @return array
	 */
	private function error_general() {
		$message = new Message();
		$message->set_level( 'error' );
		$message->set_message(
			sprintf(
				'%s - on host: %s (error code %s)',
				$this->ixr_client->getErrorMessage(),
				$this->ixr_client->server,
				$this->ixr_client->getErrorCode()
			)
		);

		return array(
			'status'   => 2,
			'messages' => array( $message ),
		);
	}

	/************************************************************************
	 * Handle Request.
	 *
	 * Operations carried out on Production to handle an incoming XML-RPC
	 * request from Content Stage.
	 ************************************************************************/

	/**
	 * Receive the XML-RPC request, authenticate and return a response.
	 * Response messages is collected from observing objects and returned as
	 * the XML-RPC response data.
	 *
	 * @param array $args
	 *
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
	 * Prepare response data.
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	public function prepare_response( $response ) {
		return $this->encode( serialize( $response ) );
	}

	/************************************************************************
	 * Common
	 ************************************************************************/

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
