<?php
namespace Me\Stenberg\Content\Staging\Models;

use Exception;

class Batch extends Model {

	/**
	 * Global unique identifier (GUID) of this batch.
	 *
	 * @var string
	 */
	private $guid;

	/**
	 * Title of this batch.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * User who created this batch.
	 *
	 * @var User
	 */
	private $creator;

	/**
	 * Date when this batch was created. Timezone according to settings of
	 * the user who created the batch.
	 *
	 * @var string
	 */
	private $date;

	/**
	 * Date when this batch was created in Greenwich Mean Time (GMT).
	 *
	 * @var string
	 */
	private $date_gmt;

	/**
	 * Date when this batch was last modified. Timezone according to settings
	 * of the user who modified the batch.
	 *
	 * @var string
	 */
	private $modified;

	/**
	 * Date when this batch was last modified in Greenwich Mean Time (GMT).
	 *
	 * @var string
	 */
	private $modified_gmt;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * Posts in this batch.
	 *
	 * @var array
	 */
	private $posts;

	/**
	 * Attachments in this batch.
	 *
	 * @var array
	 */
	private $attachments;

	/**
	 * Users in this batch.
	 *
	 * @var array
	 */
	private $users;

	/**
	 * Options in this batch.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Meta keys containing a relationship between two posts. The meta keys
	 * refers to the 'meta_key' column in the 'postmeta' database table.
	 *
	 * @var array
	 */
	private $post_rel_keys;

	/**
	 * Custom data added by third-party developer.
	 *
	 * @var array
	 */
	private $custom_data;

	/**
	 * Constructor.
	 *
	 * @param int $id
	 */
	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
		$this->meta_data     = array();
		$this->posts         = array();
		$this->attachments   = array();
		$this->users         = array();
		$this->options       = array();
		$this->post_rel_keys = array();
		$this->custom_data   = array();
	}

	/**
	 * @param string $guid
	 */
	public function set_guid( $guid ) {
		$this->guid = $guid;
	}

	/**
	 * @return string
	 */
	public function get_guid() {
		return $this->guid;
	}

	/**
	 * @param User $creator
	 */
	public function set_creator( $creator ) {
		$this->creator = $creator;
	}

	/**
	 * @return User
	 */
	public function get_creator() {
		return $this->creator;
	}

	/**
	 * @param string $date
	 */
	public function set_date( $date ) {
		$this->date = $date;
	}

	/**
	 * @return string
	 */
	public function get_date() {
		return $this->date;
	}

	/**
	 * @param string $date_gmt
	 */
	public function set_date_gmt( $date_gmt ) {
		$this->date_gmt = $date_gmt;
	}

	/**
	 * @return string
	 */
	public function get_date_gmt() {
		return $this->date_gmt;
	}

	/**
	 * @param string $modified
	 */
	public function set_modified( $modified ) {
		$this->modified = $modified;
	}

	/**
	 * @return string
	 */
	public function get_modified() {
		return $this->modified;
	}

	/**
	 * @param string $modified_gmt
	 */
	public function set_modified_gmt( $modified_gmt ) {
		$this->modified_gmt = $modified_gmt;
	}

	/**
	 * @return string
	 */
	public function get_modified_gmt() {
		return $this->modified_gmt;
	}

	/**
	 * @param string $status
	 */
	public function set_status( $status ) {
		$this->status = $status;
	}

	/**
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * @param string $title
	 */
	public function set_title( $title ) {
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * @param array $posts
	 */
	public function set_posts( array $posts ) {
		$this->posts = $posts;
	}

	/**
	 * Add a Post object to array of posts in this batch.
	 *
	 * @param Post $post
	 */
	public function add_post( Post $post ) {
		$this->posts[] = $post;
	}

	/**
	 * Get all posts in this batch.
	 *
	 * @return array
	 */
	public function get_posts() {
		return $this->posts;
	}

	/**
	 * Replace attachments with attachments in provided array.
	 *
	 * @param array $attachments
	 */
	public function set_attachments( array $attachments ) {
		$this->attachments = $attachments;
	}

	/**
	 * Add an attachment.
	 *
	 * @param array $attachment
	 */
	public function add_attachment( array $attachment ) {
		$this->attachments[] = $attachment;
	}

	/**
	 * Get all attachments in this batch.
	 *
	 * @return array
	 */
	public function get_attachments() {
		return $this->attachments;
	}

	/**
	 * @param array $users
	 */
	public function set_users( array $users ) {
		$this->users = $users;
	}

	/**
	 * Get all users in this batch.
	 *
	 * @return array
	 */
	public function get_users() {
		return $this->users;
	}

	/**
	 * Set WordPress options to be synced.
	 *
	 * @param array $options
	 */
	public function set_options( array $options ) {
		$this->options = $options;
	}

	/**
	 * Get all WordPress options in this batch.
	 *
	 * @return array
	 */
	public function get_options() {
		return $this->options;
	}

	public function set_post_rel_keys( array $keys ) {
		$this->post_rel_keys = $keys;
	}

	public function add_post_rel_key( $key ) {
		if ( ! in_array( $key, $this->post_rel_keys ) ) {
			$this->post_rel_keys[] = $key;
		}
	}

	public function get_post_rel_keys() {
		return $this->post_rel_keys;
	}

	/**
	 * Replace custom data with custom data in provided array.
	 *
	 * @param mixed $data
	 */
	public function set_custom_data( $data ) {
		$this->custom_data = $data;
	}

	/**
	 * Add custom data.
	 *
	 * Third-party developers can set a key for accessing specific data in
	 * the custom data array.
	 *
	 * @param string $key
	 * @param mixed $data
	 */
	public function add_custom_data( $key, $data ) {
		$this->custom_data[$key] = $data;
	}

	/**
	 * Get custom data in this batch.
	 *
	 * If a 'key' has been provided, only value of corresponding array key
	 * will be returned.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get_custom_data( $key = null ) {

		// No key has been provided, return all custom data.
		if ( ! $key ) {
			return $this->custom_data;
		}

		// Make sure provided key exists in our custom data array.
		if ( ! array_key_exists( $key, $this->custom_data ) ) {
			return null;
		}

		return $this->custom_data[$key];
	}

	/**
	 * Get a signature for this batch. Useful for testing if a batch has been
	 * modified.
	 *
	 * @return string
	 */
	public function get_signature() {
		return md5( serialize( $this ) );
	}

}