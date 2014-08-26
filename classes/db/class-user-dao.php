<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\DB\Mappers\User_Mapper;
use Me\Stenberg\Content\Staging\Models\User;

class User_DAO extends DAO {

	private $user_mapper;

	public function __construct( $wpdb, User_Mapper $user_mapper ) {
		parent::__constuct( $wpdb );
		$this->user_mapper = $user_mapper;
	}

	public function get_user_by_id( $user_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->users . ' WHERE ID = %d',
			$user_id
		);

		return $this->user_mapper->array_to_user_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	public function get_user_by_user_login( $user_login ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->users . ' WHERE user_login = %s',
			$user_login
		);

		return $this->user_mapper->array_to_user_object( $this->wpdb->get_row( $query, ARRAY_A ) );
	}

	/**
	 * Get users by user IDs.
	 *
	 * To fetch meta data on the users as well, set $lazy to false.
	 *
	 * @param array $user_ids
	 * @param bool $lazy
	 * @return array
	 */
	public function get_users_by_ids( $user_ids, $lazy = true ) {

		$users = array();

		$placeholders = $this->in_clause_placeholders( $user_ids, '%d' );

		if ( ! $placeholders ) {
			return $users;
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->users . ' WHERE ID IN (' . $placeholders . ')',
			$user_ids
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $user ) {
			$obj = $this->user_mapper->array_to_user_object( $user );

			// Add usermeta to user object.
			if ( ! $lazy ) {
				$obj->set_meta( $this->get_usermeta_by_user_id( $obj->get_id() ) );
			}

			$users[] = $obj;
		}

		return $users;
	}

	public function get_usermeta_by_user_id( $user_id ) {

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->usermeta . ' WHERE user_id = %d',
			$user_id
		);

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	public function get_usermeta_by_user_ids( $user_ids ) {

		$placeholders = $this->in_clause_placeholders( $user_ids, '%d' );

		if ( ! $placeholders ) {
			return array();
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->usermeta . ' WHERE user_id IN (' . $placeholders . ')',
			$user_ids
		);

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * @param User $user
	 * @return int
	 */
	public function insert_user( User $user ) {
		$data = $this->filter_user_data( $user );
		return $this->insert( 'users', $data['values'], $data['format'] );
	}

	/**
	 * @param User $user
	 * @param array $where
	 * @param array $where_format
	 */
	public function update_user( User $user, $where, $where_format ) {
		$data = $this->filter_user_data( $user );
		$this->update( 'users', $data['values'], $where, $data['format'], $where_format );
	}

	/**
	 * @param array $meta
	 * @return int
	 */
	public function insert_usermeta( $meta ) {
		$data = $this->filter_user_meta_data( $meta );
		return $this->insert( 'usermeta', $data['values'], $data['format'] );
	}

	public function update_usermeta( $data, $where, $format = null, $where_format = null ) {
		$this->update( 'usermeta', $data, $where, $format, $where_format );
	}

	public function delete_usermeta( $where, $where_format ) {
		$this->wpdb->delete( $this->wpdb->usermeta, $where, $where_format );
	}

	/**
	 * @param User $user
	 * @return array
	 */
	private function filter_user_data( User $user ) {

		$values = array();
		$format = array();

		if ( $user->get_user_login() ) {
			$values['user_login'] = $user->get_user_login();
			$format[]             = '%s';
		}

		if ( $user->get_user_pass() ) {
			$values['user_pass'] = $user->get_user_pass();
			$format[]            = '%s';
		}

		if ( $user->get_user_nicename() ) {
			$values['user_nicename'] = $user->get_user_nicename();
			$format[]                = '%s';
		}

		if ( $user->get_user_email() ) {
			$values['user_email'] = $user->get_user_email();
			$format[]             = '%s';
		}

		if ( $user->get_user_url() ) {
			$values['user_url'] = $user->get_user_url();
			$format[]           = '%s';
		}

		if ( $user->get_user_registered() ) {
			$values['user_registered'] = $user->get_user_registered();
			$format[]                  = '%s';
		}

		if ( $user->get_user_activation_key() ) {
			$values['user_activation_key'] = $user->get_user_activation_key();
			$format[]                      = '%s';
		}

		if ( $user->get_user_status() ) {
			$values['user_status'] = $user->get_user_status();
			$format[]              = '%d';
		}

		if ( $user->get_display_name() ) {
			$values['display_name'] = $user->get_display_name();
			$format[]               = '%s';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);
	}

	private function filter_user_meta_data( $meta ) {

		$values = array();
		$format = array();

		if ( isset( $meta['user_id'] ) ) {
			$values['user_id'] = $meta['user_id'];
			$format[]          = '%d';
		}

		if ( isset( $meta['meta_key'] ) ) {
			$values['meta_key'] = $meta['meta_key'];
			$format[]           = '%s';
		}

		if ( isset( $meta['meta_value'] ) ) {
			$values['meta_value'] = $meta['meta_value'];
			$format[]             = '%s';
		}

		return array(
			'values' => $values,
			'format' => $format,
		);

	}
}