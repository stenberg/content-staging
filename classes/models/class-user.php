<?php
namespace Me\Stenberg\Content\Staging\Models;

class User {

	private $id;
	private $user_login;
	private $user_pass;
	private $user_nicename;
	private $user_email;
	private $user_url;
	private $user_registered;
	private $user_activation_key;
	private $user_status;
	private $display_name;
	private $meta;

	public function __construct( $id = null ) {
		$this->set_id( $id );
	}

	/**
	 * @param int $id
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param string $display_name
	 */
	public function set_display_name( $display_name ) {
		$this->display_name = $display_name;
	}

	/**
	 * @return string
	 */
	public function get_display_name() {
		return $this->display_name;
	}

	/**
	 * @param string $user_activation_key
	 */
	public function set_user_activation_key( $user_activation_key ) {
		$this->user_activation_key = $user_activation_key;
	}

	/**
	 * @return string
	 */
	public function get_user_activation_key() {
		return $this->user_activation_key;
	}

	/**
	 * @param string $user_email
	 */
	public function set_user_email( $user_email ) {
		$this->user_email = $user_email;
	}

	/**
	 * @return string
	 */
	public function get_user_email() {
		return $this->user_email;
	}

	/**
	 * @param string $user_login
	 */
	public function set_user_login( $user_login ) {
		$this->user_login = $user_login;
	}

	/**
	 * @return string
	 */
	public function get_user_login() {
		return $this->user_login;
	}

	/**
	 * @param string $user_nicename
	 */
	public function set_user_nicename( $user_nicename ) {
		$this->user_nicename = $user_nicename;
	}

	/**
	 * @return string
	 */
	public function get_user_nicename() {
		return $this->user_nicename;
	}

	/**
	 * @param string $user_pass
	 */
	public function set_user_pass( $user_pass ) {
		$this->user_pass = $user_pass;
	}

	/**
	 * @return string
	 */
	public function get_user_pass() {
		return $this->user_pass;
	}

	/**
	 * @param string $user_registered
	 */
	public function set_user_registered( $user_registered ) {
		$this->user_registered = $user_registered;
	}

	/**
	 * @return string
	 */
	public function get_user_registered() {
		return $this->user_registered;
	}

	/**
	 * @param int $user_status
	 */
	public function set_user_status( $user_status ) {
		$this->user_status = $user_status;
	}

	/**
	 * @return int
	 */
	public function get_user_status() {
		return $this->user_status;
	}

	/**
	 * @param string $user_url
	 */
	public function set_user_url( $user_url ) {
		$this->user_url = $user_url;
	}

	/**
	 * @return string
	 */
	public function get_user_url() {
		return $this->user_url;
	}

	/**
	 * @param array $meta
	 */
	public function add_meta( $meta ) {
		$this->meta[] = $meta;
	}

	/**
	 * @param array $meta
	 */
	public function set_meta( $meta ) {
		$this->meta = $meta;
	}

	/**
	 * @return array
	 */
	public function get_meta() {
		return $this->meta;
	}

}