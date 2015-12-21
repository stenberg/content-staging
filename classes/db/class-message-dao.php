<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Message;
use Me\Stenberg\Content\Staging\Models\Model;

class Message_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
	}

	/**
	 * Get messages belonging to specific post.
	 *
	 * @param int    $post_id
	 * @param bool   $new_only Only fetch messages that has not been fetched before.
	 * @param string $type     Not supported.
	 * @param string $group    Should be set to null, preflight or deploy.
	 * @param int    $code     Not supported.
	 *
	 * @return Message
	 */
	public function get_by_post_id( $post_id, $new_only = true, $type = null, $group = null, $code = 0 ) {

		$messages   = array();
		$where_stmt = 'post_id = %d';
		$query_vars = array( $post_id );

		if ( $group ) {
			$where_stmt .= ' AND meta_key = %s';
			array_push( $query_vars, '_sme_message_' . $group );
		} else {
			$where_stmt .= ' AND (meta_key = %s OR meta_key = %s)';
			array_push( $query_vars, '_sme_message_preflight' );
			array_push( $query_vars, '_sme_message_deploy' );
		}

		// Should only messages that has not been fetched before be fetched?
		if ( $new_only ) {
			$last_message_id = $this->get_last_fetched_message_id( $post_id );

			if ( $last_message_id ) {
				$where_stmt .= ' AND meta_id > %d';
				array_push( $query_vars, $last_message_id );
			}
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->get_table() . ' WHERE ' . $where_stmt . ' ORDER BY meta_id ASC',
			$query_vars
		);

		$records = $this->wpdb->get_results( $query, ARRAY_A );

		foreach ( $records as $record ) {
			if ( is_serialized( $record['meta_value'] ) ) {
				$record['meta_value'] = unserialize( $record['meta_value'] );
			}

			array_push( $messages, $this->create_object( $record ) );
		}

		/*
		 * If only fetching new messages we should also make sure to save what
		 * the last message we fetched was so that the next time when this method
		 * is called the fetching of messages can start from the correct point.
		 */
		if ( $new_only && ! empty( $messages ) ) {
			$last_message = end( $messages );
			$this->set_last_fetched_message( $last_message );
		}

		return $messages;
	}

	/**
	 * Set last message that has been fetched.
	 *
	 * @param Message $message
	 */
	public function set_last_fetched_message( Message $message ) {
		update_post_meta( $message->get_post_id(), '_last_fetched_message', $message->get_id() );
	}

	/**
	 * Get the last message that has been fetched.
	 *
	 * @param int $post_id
	 *
	 * @return Message
	 */
	public function get_last_fetched_message_id( $post_id ) {

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->get_table() . ' WHERE post_id = %d AND meta_key = "_last_fetched_message"',
			$post_id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $row['meta_value'] ) ) {
			return $row['meta_value'];
		}

		return null;
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->wpdb->postmeta;
	}

	/**
	 * @return string
	 */
	protected function target_class() {
		return '\Me\Stenberg\Content\Staging\Models\Message';
	}

	/**
	 * @param array $raw
	 * @return string
	 */
	protected function unique_key( array $raw ) {
		return $raw['meta_id'];
	}

	/**
	 * @return string
	 */
	protected function select_stmt() {
		return 'SELECT * FROM ' . $this->get_table() . ' WHERE meta_id = %d';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return 'SELECT * FROM ' . $this->get_table() . ' WHERE meta_id in (' . $placeholders . ')';
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
	 *
	 * @return Message
	 */
	protected function do_create_object( array $raw ) {

		if ( ! isset( $raw['meta_id'] ) ) {
			return null;
		}

		$obj = new Message( $raw['meta_id'] );

		if ( isset( $raw['post_id'] ) ) {
			$obj->set_post_id( $raw['post_id'] );
		}

		if ( ! isset( $raw['meta_value'] ) ) {
			return $obj;
		}

		if ( isset( $raw['meta_value']['message'] ) ) {
			$obj->set_message( $raw['meta_value']['message'] );
		}

		if ( isset( $raw['meta_value']['code'] ) ) {
			$obj->set_code( $raw['meta_value']['code'] );
		}

		if ( isset( $raw['meta_value']['level'] ) ) {
			$obj->set_level( $raw['meta_value']['level'] );
		}

		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'post_id'    => $obj->get_post_id(),
			'meta_key'   => '',
			'meta_value' => serialize(
				array(
					'message' => $obj->get_message(),
					'code'    => $obj->get_code(),
					'level'   => $obj->get_level(),
				)
			),
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
			'%s', // post_id
			'%d', // meta_key
			'%s', // meta_value
		);
	}

}
