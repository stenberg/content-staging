<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\Post;
use Me\Stenberg\Content\Staging\Models\Post_Env_Diff;
use Exception;

class Post_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
	}

	/**
	 * Get post by global unique identifier.
	 *
	 * @param $guid
	 * @return Post
	 * @throws Exception
	 */
	public function get_by_guid( $guid ) {

		// Select post with a specific GUID ending.
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE guid = %s',
			$guid
		);

		$result = $this->wpdb->get_results( $query, ARRAY_A );

		if ( empty( $result ) ) {
			return null;
		}

		if ( count( $result ) > 1 ) {
			throw new Exception( sprintf( 'GUID %s is not unique', $guid ) );
		}

		if ( isset( $result[0] ) && isset( $result[0]['ID'] ) ) {
			return $this->create_object( $result[0] );
		}

		return null;
	}

	/**
	 * Get post by permalink components.
	 *
	 * @param Post $post
	 *
	 * @return Post
	 *
	 * @throws Exception
	 */
	public function get_by_permalink( Post $post ) {

		// Parent post ID.
		$parent_id = ( $post->get_parent() !== null ) ? $post->get_parent()->get_id() : 0;

		// Select post with a specific GUID ending.
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE post_parent = %s AND post_name = %s AND post_type = %s',
			$parent_id, $post->get_name(), $post->get_type()
		);

		$result = $this->wpdb->get_results( $query, ARRAY_A );

		if ( empty( $result ) ) {
			return null;
		}

		if ( count( $result ) > 1 ) {

			// Get all post IDs.
			$ids = array_map( function( $row ) {
				return $row['ID'];
			}, $result );

			// Turn array of IDs into string of IDs.
			$ids = implode( ', ', $ids );

			throw new Exception(
				sprintf(
					'Permalink components not unique (post_parent: %d, post_name: %s, post_type: %s). Set unique permalink for the following post IDs: %s',
					$parent_id, $post->get_name(), $post->get_type(), $ids
				)
			);
		}

		if ( isset( $result[0] ) && isset( $result[0]['ID'] ) ) {
			return $this->create_object( $result[0] );
		}

		return null;
	}

	/**
	 * Find post with the same global unique identifier (GUID) as the one for
	 * the provided post. If a match is found, return the post ID of matching
	 * post.
	 *
	 * Useful for comparing a post sent from content staging to production.
	 *
	 * @param string $guid
	 *
	 * @return int
	 */
	public function get_id_by_guid( $guid ) {

		$query = $this->wpdb->prepare(
			'SELECT ID FROM ' . $this->wpdb->posts . ' WHERE guid = %s',
			$guid
		);

		return $this->wpdb->get_var( $query );
	}

	/**
	 * Get published posts.
	 *
	 * @param array $statuses
	 * @param string $order_by
	 * @param string $order
	 * @param int $per_page
	 * @param int $paged
	 * @param array $selected
	 * @return array
	 */
	public function get_posts( $statuses = array(), $order_by = null, $order = 'asc', $per_page = 5,
							   $paged = 1, $selected = array() ) {
		$posts           = array();
		$nbr_of_selected = count( $selected );
		$limit           = $per_page;
		$values          = array();

		if ( ( $offset = ( ( $paged - 1 ) * $per_page ) - $nbr_of_selected ) < 0 ) {
			$offset = 0;
		}

		if ( ( ( ( $paged - 1 ) * $per_page ) - $nbr_of_selected ) < 0 ) {
			$limit = $per_page - $nbr_of_selected;
		}

		if ( $limit < 0 ) {
			return $posts;
		}

		// Only allow sorting results ascending or descending.
		if ( $order !== 'asc' ) {
			$order = 'desc';
		}

		$where  = 'post_type != "sme_content_batch" AND post_type != "sme_batch_import_job"';
		$where  = $this->where_statuses( $where, $statuses, $values );
		$where  = apply_filters( 'sme_query_posts_where', $where );
		$values = apply_filters( 'sme_values_posts_where', $values );
		$stmt   = 'SELECT * FROM ' . $this->wpdb->posts . ' WHERE ' . $where;

		if ( ( $nbr_of_selected = count( $selected ) ) > 0 ) {
			$placeholders = implode( ',', array_fill( 0, $nbr_of_selected, '%d' ) );
			$values       = array_merge( $values, $selected );
			$stmt        .= ' AND ID NOT IN (' . $placeholders . ')';
		}

		if ( ! is_null( $order_by ) ) {
			$stmt .= ' ORDER BY ' . $order_by . ' ' . $order;
		}

		// Adjust the query to take pagination into account.
		$stmt    .= ' LIMIT %d, %d';
		$values[] = $offset;
		$values[] = $limit;

		$query  = $this->wpdb->prepare( $stmt, $values );
		$result = ( $result = $this->wpdb->get_results( $query, ARRAY_A ) ) ? $result : array();

		foreach ( $result as $post ) {
			if ( isset( $post['ID'] ) ) {
				$posts[] = $this->create_object( $post );
			}
		}

		return $posts;
	}

	/**
	 * Get number of published content batches that exists.
	 *
	 * @param array $statuses
	 * @return int
	 */
	public function get_posts_count( $statuses = array() ) {
		$values = array();
		$where  = 'post_type != "sme_content_batch" AND post_type != "sme_batch_import_job"';
		$where  = $this->where_statuses( $where, $statuses, $values );
		$where  = apply_filters( 'sme_query_posts_where', $where );
		$values = apply_filters( 'sme_values_posts_where', $values );
		$query  = 'SELECT COUNT(*) FROM ' . $this->wpdb->posts . ' WHERE ' . $where;
		if ( ! empty( $values ) ) {
			$query = $this->wpdb->prepare( $query, $values );
		}
		return $this->wpdb->get_var( $query );
	}

	/**
	 * Get post IDs for provided post meta data.
	 *
	 * @param string $meta_key
	 * @param string $meta_value
	 *
	 * @return array
	 */
	public function get_post_ids_by_meta( $meta_key = '', $meta_value = '' ) {

		// Do not fetch any post IDs if neither key nor value has been specified.
		if ( ! $meta_key && ! $meta_value ) {
			return array();
		}

		// Query.
		$query = $this->select_post_ids_by_meta_stmt( $meta_key, $meta_value );

		// Fetch result.
		$result = $this->wpdb->get_results( $query, ARRAY_A );

		// Create numeric array with all post IDs.
		$post_ids = array_map(
			function( $post ) {
				return empty( $post['post_id'] ) ? null : (int) $post['post_id'];
			}, $result
		);

		// Filter out null values.
		return array_filter( $post_ids );
	}

	/**
	 * Select statement for retrieving post IDs for provided post meta data.
	 *
	 * @param string $meta_key
	 * @param string $meta_value
	 *
	 * @return string
	 */
	public function select_post_ids_by_meta_stmt( $meta_key, $meta_value ) {

		// Where clause in SQL query.
		$where = array();

		if ( $meta_key ) {
			$where['meta_key'] = sprintf( 'meta_key = "%s"', $meta_key );
		}

		if ( $meta_value ) {
			$where['meta_value'] = sprintf( 'meta_value = "%s"', $meta_value );
		}

		return 'SELECT post_id FROM ' . $this->wpdb->postmeta . ' WHERE ' . implode( ' AND ', $where );
	}

	/**
	 * @param Post $post
	 */
	public function update_post( Post $post ) {
		$data         = $this->create_array( $post );
		$where        = array( 'ID' => $post->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );
		$this->update( $data, $where, $format, $where_format );
	}

	/**
	 * Change post status for multiple posts.
	 *
	 * @param array $post_ids
	 * @param string $status
	 */
	public function update_post_statuses( $post_ids = array(), $status = 'publish' ) {
		if ( empty( $post_ids ) ) {
			return;
		}

		// Comma separated string with IDs of all posts to change statuses for.
		$ids = '';

		// Populate $ids string with post IDs.
		for ( $i = 0; $i < count( $post_ids ); $i++ ) {
			if ( $i !== 0 ) {
				$ids .= ',';
			}
			$ids .= $post_ids[$i];
		}

		if ( $ids ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					'UPDATE ' . $this->get_table() . ' SET post_status = %s WHERE ID in (' . $ids . ')',
					$status
				)
			);
		}
	}

	/**
	 * Change only the post status of a post.
	 *
	 * @param int $id
	 * @param string $status
	 */
	public function update_post_status( $id, $status = 'publish' ) {
		$this->update(
			array( 'post_status' => $status ),
			array( 'ID' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Change only the GUID of a post.
	 *
	 * @param $post_id
	 * @param $new_guid
	 */
	public function update_guid( $post_id, $new_guid ) {
		$this->update(
			array( 'guid' => $new_guid ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Make a post a revision of another post.
	 *
	 * @param int $revision_id Post to change to revision.
	 * @param int $parent_id Parent post of the revision.
	 */
	public function make_revision( $revision_id, $parent_id ) {
		$this->update(
			array(
				'post_status' => 'inherit',
				'post_parent' => $parent_id,
				'post_name'   => $parent_id . '-revision-v1',
				'post_type'   => 'revision',
			),
			array( 'ID' => $revision_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get post environment diff object for a batch.
	 *
	 * @param Batch $batch
	 *
	 * @return array
	 */
	public function get_post_diffs( Batch $batch ) {

		$objects = array();
		$diffs   = get_post_meta( $batch->get_id(), 'sme_post_diff' );

		if ( empty( $diffs ) ) {
			return $objects;
		}

		foreach ( $diffs as $diff ) {
			$obj = new Post_Env_Diff( $diff['stage_id'] );
			$obj->set_revision_id( $diff['revision_id'] );
			$obj->set_prod_id( $diff['prod_id'] );
			$obj->set_stage_status( $diff['stage_status'] );
			$obj->set_parent_guid( $diff['parent_guid'] );
			$objects[$diff['stage_id']] = $obj;
		}

		return $objects;
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->wpdb->posts;
	}

	/**
	 * @return string
	 */
	protected function target_class() {
		return '\Me\Stenberg\Content\Staging\Models\Post';
	}

	/**
	 * @param array $raw
	 * @return string
	 */
	protected function unique_key( array $raw ) {
		return $raw['ID'];
	}

	/**
	 * @return string
	 */
	protected function select_stmt() {
		return 'SELECT * FROM ' . $this->get_table() . ' WHERE ID = %d';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return 'SELECT * FROM ' . $this->get_table() . ' WHERE ID in (' . $placeholders . ')';
	}

	/**
	 * @param Model $obj
	 */
	protected function do_insert( Model $obj ) {
		$data   = $this->create_array( $obj );
		$format = $this->format();
		$this->wpdb->insert( $this->get_table(), $data, $format );
		$obj->set_id( $this->wpdb->insert_id );
	}

	/**
	 * @param array $raw
	 * @return Post
	 */
	protected function do_create_object( array $raw ) {
		$obj = new Post( $raw['ID'] );

		if ( ( $parent = $this->find( $raw['post_parent'] ) ) !== null ) {
			$obj->set_parent( $parent );
		}

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
		$parent = 0;

		if ( $obj->get_parent() !== null ) {
			$parent = $obj->get_parent()->get_id();
		}

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
			'post_parent'           => $parent,
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
	protected function format() {
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
