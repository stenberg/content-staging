<?php
namespace Me\Stenberg\Content\Staging\DB;

use Exception;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Model;

class Batch_DAO extends DAO {

	private $user_dao;

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
		$this->user_dao = Helper_Factory::get_instance()->get_dao( 'User' );
	}

	/**
	 * Get published batches.
	 *
	 * @param array  $statuses
	 * @param string $order_by
	 * @param string $order
	 * @param int    $per_page
	 * @param int    $paged
	 *
	 * @return array
	 */
	public function get_batches( $statuses = array(), $order_by = null, $order = 'asc', $per_page = 5, $paged = 1 ) {
		$batches = array();
		$values  = array();
		$where   = '';

		// Only allow to order the query result by the following fields.
		$allowed_order_by_values = array( 'post_title', 'post_modified', 'post_author' );

		// Make sure provided order by value is allowed.
		if ( ! in_array( $order_by, $allowed_order_by_values ) ) {
			$order_by = null;
		}

		// Only allow sorting results ascending or descending.
		if ( $order !== 'asc' ) {
			$order = 'desc';
		}

		$where = $this->where_statuses( $where, $statuses, $values );

		$stmt = 'SELECT * FROM ' . $this->wpdb->posts . ' WHERE post_type = "sme_content_batch"' . $where;

		if ( ! empty( $order_by ) && ! empty( $order ) ) {
			$stmt .= ' ORDER BY ' . $order_by . ' ' . $order;
		}

		// Adjust the query to take pagination into account.
		if ( ! empty( $paged ) && ! empty( $per_page ) ) {
			$stmt    .= ' LIMIT %d, %d';
			$values[] = ( $paged - 1 ) * $per_page;
			$values[] = $per_page;
		}

		$query = $this->wpdb->prepare( $stmt, $values );

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $batch ) {
			$batches[] = $this->create_object( $batch );
		}

		if ( $order_by == 'post_author' ) {
			usort( $batches, array( $this, 'post_author_sort' ) );
			if ( $order == 'desc' ) {
				$batches = array_reverse( $batches );
			}
		}

		return $batches;
	}

	/**
	 * Get post by global unique identifier.
	 *
	 * @param $guid
	 *
	 * @return Batch
	 *
	 * @throws Exception
	 */
	public function get_by_guid( $guid ) {

		// Select post with a specific GUID.
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE guid = %s',
			$guid
		);

		$result = $this->wpdb->get_results( $query, ARRAY_A );

		if ( empty( $result ) ) {
			return null;
		}

		if ( count( $result ) > 1 ) {
			$this->fix_duplicated_guids( $guid );
			return $this->get_by_guid( $guid );
		}

		if ( isset( $result[0] ) && isset( $result[0]['ID'] ) ) {
			return $this->create_object( $result[0] );
		}

		return null;
	}

	/**
	 * Give each batch sharing the same GUID its own GUID.
	 *
	 * Every batch should have its own GUID, if that is not the case then
	 * this method can be used to fix that.
	 *
	 * @param $guid
	 */
	public function fix_duplicated_guids( $guid ) {

		// Select posts with a specific GUID.
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->posts . ' WHERE guid = %s',
			$guid
		);

		$result = $this->wpdb->get_results( $query, ARRAY_A );

		// No duplicates exist.
		if ( count( $result ) < 2 ) {
			return;
		}

		foreach ( $result as $post ) {
			$new_guid = get_site_url( get_current_blog_id(), '/?content_staging_batch=' . $post['ID'] );
			$this->wpdb->update(
				$this->get_table(), array( 'guid' => $new_guid ), array( 'ID' => $post['ID'] ), '%s', '%d'
			);
		}
	}

	/**
	 * Get number of published content batches that exists.
	 *
	 * @param array $statuses
	 * @return int
	 */
	public function count( $statuses = array() ) {
		$values = array( 'sme_content_batch' );
		$where  = $this->where_statuses( '', $statuses, $values );
		$query  = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->wpdb->posts . ' WHERE post_type = %s' . $where,
			$values
		);
		return $this->wpdb->get_var( $query );
	}

	/**
	 * @param Batch $batch
	 */
	public function update_batch( Batch $batch ) {
		$batch->set_modified( current_time( 'mysql' ) );
		$batch->set_modified_gmt( current_time( 'mysql', 1 ) );

		$data         = $this->create_array( $batch );
		$where        = array( 'ID' => $batch->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );

		$this->update( $data, $where, $format, $where_format );
	}

	/**
	 * Delete provided batch.
	 *
	 * Set 'post_status' for provided batch to 'draft'. This will hide the
	 * batch from users, but keeping it for future references.
	 *
	 * Empty 'post_content'. Since batches can be huge this is just a
	 * precaution so we do not fill the users database with a lot of
	 * unnecessary data.
	 *
	 * @param Batch $batch
	 */
	public function delete_batch( Batch $batch ) {
		$this->update(
			array(
				'post_content' => '',
				'post_status'  => 'draft',
			),
			array(
				'ID' => $batch->get_id(),
			),
			array( '%s', '%s' ),
			array( '%d' )
		);
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
		return '\Me\Stenberg\Content\Staging\Models\Batch';
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

		$user = $this->user_dao->find( get_current_user_id() );

		$obj->set_creator( $user );
		$obj->set_date( current_time( 'mysql' ) );
		$obj->set_date_gmt( current_time( 'mysql', 1 ) );
		$obj->set_modified( $obj->get_date() );
		$obj->set_modified_gmt( $obj->get_date_gmt() );

		$array = $this->create_array( $obj );
		$id    = wp_insert_post( $array );
		$batch = get_post( $id, ARRAY_A );

		$obj->set_id( $id );

		if ( isset( $batch['guid'] ) ) {
			$obj->set_guid( $batch['guid'] );
		}
	}

	/**
	 * @param array $raw
	 * @return Batch
	 */
	protected function do_create_object( array $raw ) {

		$obj  = new Batch( $raw['ID'] );
		$user = $this->user_dao->find( $raw['post_author'] );
		$obj->set_guid( $raw['guid'] );
		$obj->set_title( $raw['post_title'] );
		$obj->set_creator( $user );
		$obj->set_date( $raw['post_date'] );
		$obj->set_date_gmt( $raw['post_date_gmt'] );
		$obj->set_modified( $raw['post_modified'] );
		$obj->set_modified_gmt( $raw['post_modified_gmt'] );
		$obj->set_status( $raw['post_status'] );
		$obj->set_backend( admin_url() );

		$content = unserialize( base64_decode( $raw['post_content'] ) );

		/*
		 * Previously $content would have contained a Batch object. This check
		 * deals with legacy batches.
		 */
		if ( ! is_array( $content ) ) {
			$content = array();
		}

		if ( isset( $content['attachments'] ) ) {
			$obj->set_attachments( $content['attachments'] );
		}

		if ( isset( $content['users'] ) ) {
			$obj->set_users( $content['users'] );
		}

		if ( isset( $content['posts'] ) ) {
			$obj->set_posts( $content['posts'] );
		}

		if ( isset( $content['custom_data'] ) ) {
			$obj->set_custom_data( $content['custom_data'] );
		}

		return $obj;
	}

	protected function do_create_array( Model $obj ) {

		$batch = array();
		$user  = $obj->get_creator();

		if ( $user !== null ) {
			$batch['post_author'] = $user->get_id();
		}

		/*
		 * Take content of a batch (attachments, users, post, custom data) and
		 * prepare it for being inserted into database.
		 */
		$content = array(
			'attachments' => $obj->get_attachments(),
			'users'       => $obj->get_users(),
			'posts'       => $obj->get_posts(),
			'custom_data' => $obj->get_custom_data(),
		);

		$content = base64_encode( serialize( $content ) );

		$batch['post_date']         = $obj->get_date();
		$batch['post_date_gmt']     = $obj->get_date_gmt();
		$batch['post_content']      = $content;
		$batch['post_title']        = $obj->get_title();
		$batch['post_status']       = $obj->get_status();
		$batch['comment_status']    = 'closed';
		$batch['ping_status']       = 'closed';
		$batch['post_name']         = '';
		$batch['post_modified']     = $obj->get_modified();
		$batch['post_modified_gmt'] = $obj->get_modified_gmt();
		$batch['guid']              = $obj->get_guid();
		$batch['post_type']         = 'sme_content_batch';

		return $batch;
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
			'%s', // post_status
			'%s', // comment_status
			'%s', // ping_status
			'%s', // post_name
			'%s', // post_modified
			'%s', // post_modified_gmt
			'%s', // guid
			'%s', // post_type
		);
	}

	/**
	 * Sort batches by the display name of batch creators.
	 *
	 * @param Batch $a
	 * @param Batch $b
	 * @return int
	 */
	private function post_author_sort( Batch $a, Batch $b ) {
		return $a->get_creator()->get_display_name() == $b->get_creator()->get_display_name() ? 0 : ( $a->get_creator()->get_display_name() > $b->get_creator()->get_display_name() ) ? 1 : -1;
	}

}
