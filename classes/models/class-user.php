<?php
namespace Me\Stenberg\Content\Staging\Models;

class User extends Model {

	private $login;
	private $password;
	private $nicename;
	private $email;
	private $url;
	private $registered;
	private $activation_key;
	private $status;
	private $display_name;
	private $meta;

	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
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
	public function set_activation_key( $user_activation_key ) {
		$this->activation_key = $user_activation_key;
	}

	/**
	 * @return string
	 */
	public function get_activation_key() {
		return $this->activation_key;
	}

	/**
	 * @param string $user_email
	 */
	public function set_email( $user_email ) {
		$this->email = $user_email;
	}

	/**
	 * @return string
	 */
	public function get_email() {
		return $this->email;
	}

	/**
	 * @param string $user_login
	 */
	public function set_login( $user_login ) {
		$this->login = $user_login;
	}

	/**
	 * @return string
	 */
	public function get_login() {
		return $this->login;
	}

	/**
	 * @param string $user_nicename
	 */
	public function set_nicename( $user_nicename ) {
		$this->nicename = $user_nicename;
	}

	/**
	 * @return string
	 */
	public function get_nicename() {
		return $this->nicename;
	}

	/**
	 * @param string $user_pass
	 */
	public function set_password( $user_pass ) {
		$this->password = $user_pass;
	}

	/**
	 * @return string
	 */
	public function get_password() {
		return $this->password;
	}

	/**
	 * @param string $user_registered
	 */
	public function set_registered( $user_registered ) {
		$this->registered = $user_registered;
	}

	/**
	 * @return string
	 */
	public function get_registered() {
		return $this->registered;
	}

	/**
	 * @param int $user_status
	 */
	public function set_status( $user_status ) {
		$this->status = $user_status;
	}

	/**
	 * @return int
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * @param string $user_url
	 */
	public function set_url( $user_url ) {
		$this->url = $user_url;
	}

	/**
	 * @return string
	 */
	public function get_url() {
		return $this->url;
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