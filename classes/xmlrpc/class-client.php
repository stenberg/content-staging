<?php
namespace Me\Stenberg\Content\Staging\XMLRPC;

use Me\Stenberg\Patterns\Observer\Observable;
use Me\Stenberg\Patterns\Observer\Observer;
use \WP_HTTP_IXR_Client;

class Client implements Observable {

	private $server;
	private $secret_key;
	private $wp_http_ixr_client;
	private $observers;
	private $request;
	private $response;

	public function __construct( $server, $secret_key ) {
		$this->server = $server;
		$this->secret_key = $secret_key;
		$this->wp_http_ixr_client = new WP_HTTP_IXR_Client( trailingslashit( $server ) . 'xmlrpc.php', false, false, CONTENT_STAGING_XMLRPC_TIMEOUT );
		$this->observers = array();
	}

	/**
	 * Attach an object that should be notified on specific changes to this
	 * object.
	 *
	 * @param Observer $observer
	 */
	public function attach( Observer $observer ) {
		$this->observers[] = $observer;
	}

	/**
	 * Detach a registered observer.
	 *
	 * @param Observer $observer
	 */
	public function detach( Observer $observer ) {
		$new_observers = array();
		foreach ( $this->observers as $obs ) {
			if ( ($obs !== $observer ) ) {
				$new_observers[] = $obs;
			}
		}
		$this->observers = $new_observers;
	}

	/**
	 * Notify all observers that a XML-RPC request has been received. Collect
	 * responses from observers that will be used as XML-RPC response data.
	 *
	 * @return array
	 */
	public function notify() {

		// Messages to return as the XML-RPC response.
		$responses = array();

		foreach ( $this->observers as $observer ) {

			// Notify the observer about the XML-RPC request.
			$response = $observer->update( $this );

			// Search for messages in the response we got from the observer.
			foreach ( $response as $level => $messages ) {
				if ( ! array_key_exists( $level, $responses ) ) {
					$responses[$level] = array();
				}

				$responses[$level] = array_merge( $responses[$level], $messages );
			}
		}

		return $responses;
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
			/*
			 * @todo No response! Give all possible feedback to user, e.g. could it be that
			 * server address is wrong? Print server address.
			 */
			$this->response = array(
				'error' => array(
					$this->wp_http_ixr_client->getErrorMessage() . ' - on host: ' . $this->server . ' (error code ' . $this->wp_http_ixr_client->getErrorCode() . ')'
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
	 * @return array The XML-RPC response data to the incoming request.
	 */
	public function request( $args ) {

		if ( ! isset( $args[0] ) ) {
			return $this->prepare_response(
				array( 'error' => array( 'No access token has been provided. Request failed.' ) )
			);
		}

		if ( ! isset( $args[1] ) ) {
			return $this->prepare_response(
				array( 'error' => array( 'No data has been provided. Request failed.' ) )
			);
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
			return $this->prepare_response(
				array( 'error' => array( $msg ) )
			);
		}

		// Get the request data.
		$this->request = unserialize( $this->decode( $data ) );

		/*
		 * Notify any observing objects that a request from content stage has
		 * been received by the production environment.
		 *
		 * The observers will return messages that in turn will be returned as
		 * the XML-RPC response data.
		 */
		$messages = $this->notify();

		// Prepare and return the XML-RPC response data.
		return $this->prepare_response( $messages );
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
	private function prepare_response( $response ) {
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
