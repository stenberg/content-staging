<?php
namespace Me\Stenberg\Content\Staging\Models;

use Me\Stenberg\Content\Staging\Models\Relationships\Post_Taxonomy;

class Post extends Model {

	private $author;
	private $date;
	private $date_gmt;
	private $modified;
	private $modified_gmt;
	private $content;
	private $title;
	private $excerpt;
	private $post_status;
	private $comment_status;
	private $ping_status;
	private $password;
	private $name;
	private $to_ping;
	private $pinged;
	private $content_filtered;
	private $parent;
	private $guid;
	private $menu_order;
	private $type;
	private $mime_type;
	private $comment_count;
	private $meta;
	private $post_taxonomy_relationships;

	/**
	 * Constructor.
	 */
	public function __construct( $id = null ) {
		parent::__construct( (int) $id );
		$this->meta                        = array();
		$this->post_taxonomy_relationships = array();
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
	public function set_author( $post_author ) {
		$this->author = $post_author;
	}

	/**
	 * @return int
	 */
	public function get_author() {
		return $this->author;
	}

	/**
	 * @param string $post_content
	 */
	public function set_content( $post_content ) {
		$this->content = $post_content;
	}

	/**
	 * @return string
	 */
	public function get_content() {
		return $this->content;
	}

	/**
	 * @param string $post_content_filtered
	 */
	public function set_content_filtered( $post_content_filtered ) {
		$this->content_filtered = $post_content_filtered;
	}

	/**
	 * @return string
	 */
	public function get_content_filtered() {
		return $this->content_filtered;
	}

	/**
	 * @param string $post_date
	 */
	public function set_date( $post_date ) {
		$this->date = $post_date;
	}

	/**
	 * @return string
	 */
	public function get_date() {
		return $this->date;
	}

	/**
	 * @param string $post_date_gmt
	 */
	public function set_date_gmt( $post_date_gmt ) {
		$this->date_gmt = $post_date_gmt;
	}

	/**
	 * @return string
	 */
	public function get_date_gmt() {
		return $this->date_gmt;
	}

	/**
	 * @param string $post_modified
	 */
	public function set_modified( $post_modified ) {
		$this->modified = $post_modified;
	}

	/**
	 * @return string
	 */
	public function get_modified() {
		return $this->modified;
	}

	/**
	 * @param string $post_modified_gmt
	 */
	public function set_modified_gmt( $post_modified_gmt ) {
		$this->modified_gmt = $post_modified_gmt;
	}

	/**
	 * @return string
	 */
	public function get_modified_gmt() {
		return $this->modified_gmt;
	}

	/**
	 * @param string $post_excerpt
	 */
	public function set_excerpt( $post_excerpt ) {
		$this->excerpt = $post_excerpt;
	}

	/**
	 * @return string
	 */
	public function get_excerpt() {
		return $this->excerpt;
	}

	/**
	 * @param string $post_mime_type
	 */
	public function set_mime_type( $post_mime_type ) {
		$this->mime_type = $post_mime_type;
	}

	/**
	 * @return string
	 */
	public function get_mime_type() {
		return $this->mime_type;
	}

	/**
	 * @param mixed $post_name
	 */
	public function set_name( $post_name )
	{
		$this->name = $post_name;
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @param Post $post
	 */
	public function set_parent( Post $post ) {
		$this->parent = $post;
	}

	/**
	 * @return Post
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * @param string $post_password
	 */
	public function set_password( $post_password ) {
		$this->password = $post_password;
	}

	/**
	 * @return string
	 */
	public function get_password() {
		return $this->password;
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
	public function set_title( $post_title ) {
		$this->title = $post_title;
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * @param string $post_type
	 */
	public function set_type( $post_type ) {
		$this->type = $post_type;
	}

	/**
	 * @return mixed
	 */
	public function get_type() {
		return $this->type;
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
	public function set_meta( array $meta ) {
		$this->meta = $meta;
	}

	/**
	 * @param array $meta
	 */
	public function add_meta( array $meta ) {
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
