<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\DB\Mappers\Post_Mapper;
use Me\Stenberg\Content\Staging\Models\Post;

class Post_DAO extends DAO {

	private $post_mapper;

	public function __construct( $wpdb, Post_Mapper $post_mapper ) {
		parent::__constuct( $wpdb );

		$this->post_mapper = $post_mapper;
	}

	/**
	 * Get post by id.
	 *
	 * @param $id
	 * @return Post
	 */
	public function get_post_by_id( $id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE ID = %d',
			$id
		);

		return $this->post_mapper->array_to_post_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get post by global unique identifier.
	 *
	 * @param $guid
	 * @return Post
	 */
	public function get_post_by_guid( $guid ) {

		$guid = $this->normalize_guid( $guid );

		// Select post with a specific GUID ending.
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE guid LIKE %s',
			'%' . $guid
		);

		return $this->post_mapper->array_to_post_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Find post with the same global unique identifier (GUID) as the one for
	 * the provided post. If a match is found, update provided post with the
	 * post ID we got from database.
	 *
	 * Useful for comparing a post sent from content staging to production.
	 *
	 * @param Post $post
	 */
	public function get_id_by_guid( Post $post ) {

		$query = $this->wpdb->prepare(
			'SELECT ID FROM ' . $this->wpdb->posts . ' WHERE guid = %s',
			$post->get_guid()
		);

		$post->set_id( $this->wpdb->get_var( $query ) );
	}

	/**
	 * Get global unique identifier (GUID) for post with provided ID. Return
	 * null if no post with provided ID is found.
	 *
	 * @param int $post_id
	 * @return string|null Return GUID if found, null otherwise.
	 */
	public function get_guid_by_id( $post_id ) {

		// Check if a parent post exists.
		if ( $post_id <= 0 ) {
			return null;
		}

		$query = $this->wpdb->prepare(
			'SELECT guid FROM ' . $this->wpdb->posts . ' WHERE ID = %d',
			$post_id
		);

		$post = $this->post_mapper->array_to_post_object( $this->wpdb->get_row( $query, ARRAY_A ) );

		if ( $post !== null ) {
			return $post->get_guid();
		}

		return null;
	}

	/**
	 * @param array $ids
	 * @return array
	 */
	public function get_posts_by_ids( $ids ) {

		$posts        = array();
		$placeholders = $this->in_clause_placeholders( $ids, '%d' );

		if ( ! $placeholders ) {
			return array();
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE ID in (' . $placeholders . ')',
			$ids
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $post ) {
			$posts[] = $this->post_mapper->array_to_post_object( $post );
		}

		return $posts;
	}

	/**
	 * Get published posts.
	 *
	 * @param string $order_by
	 * @param string $order
	 * @param int $per_page
	 * @param int $paged
	 * @return array
	 */
	public function get_published_posts( $order_by = null, $order = 'asc', $per_page = 5, $paged = 1 ) {

		$posts = array();

		// Only allow to order the query result by the following fields.
		$allowed_order_by_values = array( 'post_title', 'post_modified' );

		// Make sure provided order by value is allowed.
		if ( ! in_array( $order_by, $allowed_order_by_values ) ) {
			$order_by = null;
		}

		// Only allow sorting results ascending or descending.
		if ( $order !== 'asc' ) {
			$order = 'desc';
		}

		$stmt   = 'SELECT * FROM ' . $this->wpdb->posts . ' WHERE post_type != "sme_content_batch" AND post_status = "publish"';
		$values = array();

		if ( ! empty( $order_by ) && ! empty( $order ) ) {
			$stmt    .= ' ORDER BY ' . $order_by . ' ' . $order;
		}

		// Adjust the query to take pagination into account.
		if ( ! empty( $paged ) && ! empty( $per_page ) ) {
			$stmt    .= ' LIMIT %d, %d';
			$values[] = ( $paged - 1 ) * $per_page;
			$values[] = $per_page;
		}

		$query = $this->wpdb->prepare( $stmt, $values );

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $post ) {
			$posts[] = $this->post_mapper->array_to_post_object( $post );
		}

		return $posts;
	}

	/**
	 * Get number of published posts that exists.
	 *
	 * @return int
	 */
	public function get_published_posts_count() {
		return $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->wpdb->posts . ' WHERE post_type != "sme_content_batch" AND post_status = "publish"' );
	}

	/**
	 * Get published posts that is newer then provided date.
	 *
	 * @param string $date
	 * @return array
	 */
	public function get_published_posts_newer_then_modification_date( $date ) {

		$posts = array();

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' ' .
			'WHERE post_status = "publish" AND post_type != "sme_content_batch" AND post_modified > %s ' .
			'ORDER BY post_type ASC',
			$date
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $post ) {
			$posts[] = $this->post_mapper->array_to_post_object( $post );
		}

		return $posts;
	}

	/**
	 * @param Post $post
	 */
	public function insert_post( Post $post ) {

		$data = array(
			'post_author'           => $post->get_post_author(),
			'post_date'             => $post->get_post_date(),
			'post_date_gmt'         => $post->get_post_date_gmt(),
			'post_content'          => $post->get_post_content(),
			'post_title'            => $post->get_post_title(),
			'post_excerpt'          => $post->get_post_excerpt(),
			'post_status'           => $post->get_post_status(),
			'comment_status'        => $post->get_comment_status(),
			'ping_status'           => $post->get_ping_status(),
			'post_password'         => $post->get_post_password(),
			'post_name'             => $post->get_post_name(),
			'to_ping'               => $post->get_to_ping(),
			'pinged'                => $post->get_pinged(),
			'post_modified'         => $post->get_post_modified(),
			'post_modified_gmt'     => $post->get_post_modified_gmt(),
			'post_content_filtered' => $post->get_post_content_filtered(),
			'post_parent'           => $post->get_post_parent(),
			'guid'                  => $post->get_guid(),
			'menu_order'            => $post->get_menu_order(),
			'post_type'             => $post->get_post_type(),
			'post_mime_type'        => $post->get_post_mime_type(),
			'comment_count'         => $post->get_comment_count()
		);

		$format =  array(
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%d',
			'%s',
			'%s',
			'%d'
		);

		$post->set_id( $this->insert( 'posts', $data, $format ) );
	}

	/**
	 * @param Post $post
	 */
	public function update_post( Post $post ) {

		$data = array(
			'post_author'           => $post->get_post_author(),
			'post_date'             => $post->get_post_date(),
			'post_date_gmt'         => $post->get_post_date_gmt(),
			'post_content'          => $post->get_post_content(),
			'post_title'            => $post->get_post_title(),
			'post_excerpt'          => $post->get_post_excerpt(),
			'post_status'           => $post->get_post_status(),
			'comment_status'        => $post->get_comment_status(),
			'ping_status'           => $post->get_ping_status(),
			'post_password'         => $post->get_post_password(),
			'post_name'             => $post->get_post_name(),
			'to_ping'               => $post->get_to_ping(),
			'pinged'                => $post->get_pinged(),
			'post_modified'         => $post->get_post_modified(),
			'post_modified_gmt'     => $post->get_post_modified_gmt(),
			'post_content_filtered' => $post->get_post_content_filtered(),
			'post_parent'           => $post->get_post_parent(),
			'guid'                  => $post->get_guid(),
			'menu_order'            => $post->get_menu_order(),
			'post_type'             => $post->get_post_type(),
			'post_mime_type'        => $post->get_post_mime_type(),
			'comment_count'         => $post->get_comment_count()
		);

		$where = array( 'ID' => $post->get_id() );

		$format = array(
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%d',
			'%s',
			'%s',
			'%d'
		);

		$where_format = array( '%d' );


		$this->update( 'posts', $data, $where, $format, $where_format );
	}

}
