<?php
namespace Me\Stenberg\Content\Staging;

use Exception;

class Helper_Factory {

	private $wpdb;
	private $mappers;
	private $clients;

	private static $instance;

	private function __construct( $wpdb ) {
		$this->wpdb    = $wpdb;
		$this->mappers = array();
		$this->clients = array();
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			global $wpdb;
			self::$instance = new Helper_Factory( $wpdb );
		}
		return self::$instance;
	}

	public function get_dao( $key ) {
		$mapper = 'Me\Stenberg\Content\Staging\DB\\' . $key . '_DAO';

		if ( isset( $this->mappers[$mapper] ) ) {
			return $this->mappers[$mapper];
		}

		if ( ! class_exists( $mapper ) ) {
			throw new Exception( 'Class ' . $mapper . ' not found' );
		}

		$this->mappers[$mapper] = new $mapper( $this->wpdb );
		return $this->mappers[$mapper];
	}

	public function get_client() {
		$client = 'Me\Stenberg\Content\Staging\XMLRPC\Client';

		if ( isset( $this->clients[$client] ) ) {
			return $this->clients[$client];
		}

		if ( ! class_exists( $client ) ) {
			throw new Exception( 'Class ' . $client . ' not found' );
		}

		$this->clients[$client] = new $client( $this->wpdb );
		return $this->clients[$client];
	}

}