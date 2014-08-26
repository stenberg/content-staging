<?php
namespace Me\Stenberg\Content\Staging\Models;

use stdClass;

class Batch {

	/**
	 * ID of this batch.
	 *
	 * @var int
	 */
	private $id;

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
	 * Content of this batch.
	 *
	 * @todo Should not be in here.
	 * @var string
	 */
	private $content;

	/**
	 * ID of user who created this batch.
	 * @var
	 */
	private $creator_id;

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
	 * Terms used in any of the posts in this batch.
	 *
	 * @var array
	 */
	private $terms;

	/**
	 * Constructor.
	 *
	 * @param int $id
	 */
	public function __construct( $id = null ) {
		$this->id             = $id;
		$this->posts          = array();
		$this->attachment_ids = array();
		$this->users          = array();
	}

	/**
	 * @param int $id
	 */
	public function set_id( $id ) {
		$this->id = (int) $id;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
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
	 * @param int $creator_id
	 */
	public function set_creator_id( $creator_id ) {
		$this->creator_id = (int) $creator_id;
	}

	/**
	 * @return int
	 */
	public function get_creator_id() {
		return $this->creator_id;
	}

	/**
	 * @param string $content
	 */
	public function set_content( $content ) {
		$this->content = $content;
	}

	/**
	 * @return string
	 */
	public function get_content() {
		return $this->content;
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
	 * Add an attachment.
	 *
	 * @param array $attachment
	 */
	public function add_attachment( $attachment ) {
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
	public function set_users( $users ) {
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
	 * @param array $terms
	 */
	public function set_terms( $terms ) {
		$this->terms = $terms;
	}

	/**
	 * @return array
	 */
	public function get_terms() {
		return $this->terms;
	}

}