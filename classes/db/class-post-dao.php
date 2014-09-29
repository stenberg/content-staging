<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Post;

class Post_DAO extends DAO {

	private $table;

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
		$this->table = $wpdb->posts;
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

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['ID'] ) ) {
			return $this->create_object( $result );
		}

		return null;
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

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['ID'] ) ) {
			return $this->create_object( $result );
		}

		return null;
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

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['guid'] ) ) {
			return $result['guid'];
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
			if ( isset( $post['ID'] ) ) {
				$posts[] = $this->create_object( $post );
			}
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
			if ( isset( $post['ID'] ) ) {
				$posts[] = $this->create_object( $post );
			}
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
			if ( isset( $post['ID'] ) ) {
				$posts[] = $this->create_object( $post );
			}
		}

		return $posts;
	}

	/**
	 * @param Post $post
	 */
	public function insert_post( Post $post ) {
		$data   = $this->create_array( $post );
		$format = $this->format();
		$this->wpdb->insert( $this->table, $data, $format );
		$post->set_id( $this->wpdb->insert_id );
	}

	/**
	 * @param Post $post
	 */
	public function update_post( Post $post ) {
		$data         = $this->create_array( $post );
		$where        = array( 'ID' => $post->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );
		$this->wpdb->update( $this->table, $data, $where, $format, $where_format );
	}

	/**
	 * @param array $raw
	 * @return Post
	 */
	protected function do_create_object( array $raw ) {
		$obj = new Post( $raw['ID'] );
		$obj->set_author( $raw['post_author'] );
		$obj->set_date( $raw['post_date'] );
		$obj->set_date_gmt( $raw['post_date_gmt'] );
		$obj->set_modified( $raw['post_modified'] );
		$obj->set_modified_gmt( $raw['post_modified_gmt'] );
		$obj->set_content( $raw['post_content'] );
		$obj->set_title( $raw['post_title'] );
		$obj->set_excerpt( $raw['post_excerpt'] );
		$obj->set_post_status( $raw['post_status'] );
		$obj->set_comment_status( $raw['comment_status'] );
		$obj->set_ping_status( $raw['ping_status'] );
		$obj->set_password( $raw['post_password'] );
		$obj->set_name( $raw['post_name'] );
		$obj->set_to_ping( $raw['to_ping'] );
		$obj->set_pinged( $raw['pinged'] );
		$obj->set_content_filtered( $raw['post_content_filtered'] );
		$obj->set_parent( $raw['post_parent'] );
		$obj->set_guid( $raw['guid'] );
		$obj->set_menu_order( $raw['menu_order'] );
		$obj->set_type( $raw['post_type'] );
		$obj->set_mime_type( $raw['post_mime_type'] );
		$obj->set_comment_count( $raw['comment_count'] );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'post_author'           => $obj->get_author(),
			'post_date'             => $obj->get_date(),
			'post_date_gmt'         => $obj->get_date_gmt(),
			'post_content'          => $obj->get_content(),
			'post_title'            => $obj->get_title(),
			'post_excerpt'          => $obj->get_excerpt(),
			'post_status'           => $obj->get_post_status(),
			'comment_status'        => $obj->get_comment_status(),
			'ping_status'           => $obj->get_ping_status(),
			'post_password'         => $obj->get_password(),
			'post_name'             => $obj->get_name(),
			'to_ping'               => $obj->get_to_ping(),
			'pinged'                => $obj->get_pinged(),
			'post_modified'         => $obj->get_modified(),
			'post_modified_gmt'     => $obj->get_modified_gmt(),
			'post_content_filtered' => $obj->get_content_filtered(),
			'post_parent'           => $obj->get_parent(),
			'guid'                  => $obj->get_guid(),
			'menu_order'            => $obj->get_menu_order(),
			'post_type'             => $obj->get_type(),
			'post_mime_type'        => $obj->get_mime_type(),
			'comment_count'         => $obj->get_comment_count(),
		);
	}

	/**
	 * Format of each of the values in the result set.
	 *
	 * Important! Must mimic the array returned by the
	 * 'do_create_array' method.
	 *
	 * @return array
	 */
	private function format() {
		return array(
			'%d', // post_author
			'%s', // post_date
			'%s', // post_date_gmt
			'%s', // post_content
			'%s', // post_title
			'%s', // post_excerpt
			'%s', // post_status
			'%s', // comment_status
			'%s', // ping_status
			'%s', // post_password
			'%s', // post_name
			'%s', // to_ping
			'%s', // pinged
			'%s', // post_modified
			'%s', // post_modified_gmt
			'%s', // post_content_filtered
			'%d', // post_parent
			'%s', // guid
			'%d', // menu_order
			'%s', // post_type
			'%s', // post_mime_type
			'%d', // comment_count
		);
	}

}
