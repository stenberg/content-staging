<?php
namespace Me\Stenberg\Content\Staging\Models;

use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;

class Post {

	private $id;
	private $post_author;
	private $post_date;
	private $post_date_gmt;
	private $post_content;
	private $post_title;
	private $post_excerpt;
	private $post_status;
	private $comment_status;
	private $ping_status;
	private $post_password;
	private $post_name;
	private $to_ping;
	private $pinged;
	private $post_modified;
	private $post_modified_gmt;
	private $post_content_filtered;
	private $post_parent;
	private $post_parent_guid;
	private $guid;
	private $menu_order;
	private $post_type;
	private $post_mime_type;
	private $comment_count;
	private $meta;
	private $post_taxonomy_relationships;

	/**
	 * Constructor.
	 */
	public function __construct( $id = null ) {
		$this->set_id( $id );

		$this->meta                        = array();
		$this->post_taxonomy_relationships = array();
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
	 * @param int $comment_count
	 */
	public function set_comment_count( $comment_count ) {
		$this->comment_count = $comment_count;
	}

	/**
	 * @return int
	 */
	public function get_comment_count() {
		return $this->comment_count;
	}

	/**
	 * @param string $comment_status
	 */
	public function set_comment_status( $comment_status ) {
		$this->comment_status = $comment_status;
	}

	/**
	 * @return string
	 */
	public function get_comment_status() {
		return $this->comment_status;
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
	 * @param int $menu_order
	 */
	public function set_menu_order( $menu_order ) {
		$this->menu_order = $menu_order;
	}

	/**
	 * @return int
	 */
	public function get_menu_order() {
		return $this->menu_order;
	}

	/**
	 * @param string $ping_status
	 */
	public function set_ping_status( $ping_status ) {
		$this->ping_status = $ping_status;
	}

	/**
	 * @return string
	 */
	public function get_ping_status() {
		return $this->ping_status;
	}

	/**
	 * @param string $pinged
	 */
	public function set_pinged( $pinged ) {
		$this->pinged = $pinged;
	}

	/**
	 * @return string
	 */
	public function get_pinged() {
		return $this->pinged;
	}

	/**
	 * @param int $post_author
	 */
	public function set_post_author( $post_author ) {
		$this->post_author = $post_author;
	}

	/**
	 * @return int
	 */
	public function get_post_author() {
		return $this->post_author;
	}

	/**
	 * @param string $post_content
	 */
	public function set_post_content( $post_content ) {
		$this->post_content = $post_content;
	}

	/**
	 * @return string
	 */
	public function get_post_content() {
		return $this->post_content;
	}

	/**
	 * @param string $post_content_filtered
	 */
	public function set_post_content_filtered( $post_content_filtered ) {
		$this->post_content_filtered = $post_content_filtered;
	}

	/**
	 * @return string
	 */
	public function get_post_content_filtered() {
		return $this->post_content_filtered;
	}

	/**
	 * @param string $post_date
	 */
	public function set_post_date( $post_date ) {
		$this->post_date = $post_date;
	}

	/**
	 * @return string
	 */
	public function get_post_date() {
		return $this->post_date;
	}

	/**
	 * @param string $post_date_gmt
	 */
	public function set_post_date_gmt( $post_date_gmt ) {
		$this->post_date_gmt = $post_date_gmt;
	}

	/**
	 * @return string
	 */
	public function get_post_date_gmt() {
		return $this->post_date_gmt;
	}

	/**
	 * @param string $post_excerpt
	 */
	public function set_post_excerpt( $post_excerpt ) {
		$this->post_excerpt = $post_excerpt;
	}

	/**
	 * @return string
	 */
	public function get_post_excerpt() {
		return $this->post_excerpt;
	}

	/**
	 * @param string $post_mime_type
	 */
	public function set_post_mime_type( $post_mime_type ) {
		$this->post_mime_type = $post_mime_type;
	}

	/**
	 * @return string
	 */
	public function get_post_mime_type() {
		return $this->post_mime_type;
	}

	/**
	 * @param string $post_modified
	 */
	public function set_post_modified( $post_modified ) {
		$this->post_modified = $post_modified;
	}

	/**
	 * @return string
	 */
	public function get_post_modified() {
		return $this->post_modified;
	}

	/**
	 * @param string $post_modified_gmt
	 */
	public function set_post_modified_gmt( $post_modified_gmt ) {
		$this->post_modified_gmt = $post_modified_gmt;
	}

	/**
	 * @return string
	 */
	public function get_post_modified_gmt() {
		return $this->post_modified_gmt;
	}

	/**
	 * @param mixed $post_name
	 */
	public function set_post_name( $post_name )
	{
		$this->post_name = $post_name;
	}

	/**
	 * @return string
	 */
	public function get_post_name() {
		return $this->post_name;
	}

	/**
	 * @param int $post_parent
	 */
	public function set_post_parent( $post_parent ) {
		$this->post_parent = $post_parent;
	}

	/**
	 * @return int
	 */
	public function get_post_parent() {
		return $this->post_parent;
	}

	/**
	 * @param string $post_parent_guid
	 */
	public function set_post_parent_guid( $post_parent_guid ) {
		$this->post_parent_guid = $post_parent_guid;
	}

	/**
	 * @return string
	 */
	public function get_post_parent_guid() {
		return $this->post_parent_guid;
	}

	/**
	 * @param string $post_password
	 */
	public function set_post_password( $post_password ) {
		$this->post_password = $post_password;
	}

	/**
	 * @return string
	 */
	public function get_post_password() {
		return $this->post_password;
	}

	/**
	 * @param string $post_status
	 */
	public function set_post_status( $post_status ) {
		$this->post_status = $post_status;
	}

	/**
	 * @return string
	 */
	public function get_post_status() {
		return $this->post_status;
	}

	/**
	 * @param string $post_title
	 */
	public function set_post_title( $post_title ) {
		$this->post_title = $post_title;
	}

	/**
	 * @return string
	 */
	public function get_post_title() {
		return $this->post_title;
	}

	/**
	 * @param string $post_type
	 */
	public function set_post_type( $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * @return mixed
	 */
	public function get_post_type() {
		return $this->post_type;
	}

	/**
	 * @param string $to_ping
	 */
	public function set_to_ping( $to_ping ) {
		$this->to_ping = $to_ping;
	}

	/**
	 * @return string
	 */
	public function get_to_ping() {
		return $this->to_ping;
	}

	/**
	 * @param array $meta
	 */
	public function add_meta( $meta ) {
		$this->meta[] = $meta;
	}

	/**
	 * @return array
	 */
	public function get_meta() {
		return $this->meta;
	}

	/**
	 * @param Post_Taxonomy $post_taxonomy
	 */
	public function add_post_taxonomy( Post_Taxonomy $post_taxonomy ) {

		/*
		 * Update Post_Taxonomy if it is already a part of the
		 * 'post_taxonomy_relationships' array.
		 */
		foreach ( $this->post_taxonomy_relationships as $i => $existing_post_taxonomy ) {
			if ( $post_taxonomy === $existing_post_taxonomy ) {
				$this->post_taxonomy_relationships[$i] = $post_taxonomy;
				return;
			}
		}

		/*
		 * This Post_Taxonomy is new to the 'post_taxonomy_relationships' array,
		 * add it.
		 */
		$this->post_taxonomy_relationships[] = $post_taxonomy;
	}

	/**
	 * @return array
	 */
	public function get_post_taxonomy_relationships() {
		return $this->post_taxonomy_relationships;
	}

}
