<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\User;

class User_DAO extends DAO {

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
	}

	/**
	 * @param string $user_login
	 * @return User
	 */
	public function get_user_by_user_login( $user_login ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->get_table() . ' WHERE user_login = %s',
			$user_login
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['ID'] ) ) {
			return $this->create_object( $result );
		}

		return null;
	}

	/**
	 * @param string $user_login
	 *
	 * @return int
	 */
	public function get_user_id_by_user_login( $user_login ) {

		$query = $this->wpdb->prepare(
			'SELECT ID FROM ' . $this->get_table() . ' WHERE user_login = %s',
			$user_login
		);

		return $this->wpdb->get_var( $query );
	}

	/**
	 * @param User $user
	 */
	public function update_user( User $user ) {
		$data         = $this->create_array( $user );
		$where        = array( 'ID' => $user->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );

		$this->update( $data, $where, $format, $where_format );
		$this->update_all_user_meta( $user );
	}

	/**
	 * @param User $user
	 */
	public function insert_all_user_meta( User $user ) {
		$placeholders = '';
		$values       = array();

		foreach ( $user->get_meta() as $index => $meta ) {
			if ( $index !== 0 ) {
				$placeholders .= ',';
			}
			$placeholders .= '(%d,%s,%s)';
			$values[] = $user->get_id();
			$values[] = $meta['meta_key'];
			$values[] = $meta['meta_value'];
		}

		if ( ! empty( $values ) ) {
			$query = $this->wpdb->prepare(
				'INSERT INTO ' . $this->wpdb->usermeta . ' (user_id, meta_key, meta_value) ' .
				'VALUES ' . $placeholders,
				$values
			);

			$this->wpdb->query( $query );
		}
	}

	/**
	 * Update user meta.
	 *
	 * @param User $user
	 */
	public function update_all_user_meta( User $user ) {

		$insert_keys = array();
		$stage_keys  = array();

		$insert = array();
		$update = array();
		$delete = array();

		$stage_records = $user->get_meta();
		$prod_records  = $this->get_user_meta( $user->get_id() );

		/*
		 * Go through each meta record we got from stage. If a meta_key exists
		 * more then once, then we will not try to update any records with that
		 * meta key.
		 */
		foreach ( $stage_records as $key => $prod_record ) {
			if ( in_array( $prod_record['meta_key'], $stage_keys ) ) {
				$insert[]      = $prod_record;
				$insert_keys[] = $prod_record['meta_key'];
				unset( $stage_records[$key] );
			} else {
				$stage_keys[] = $prod_record['meta_key'];
			}
		}

		/*
		 * Go through each meta record we got from production. If a meta_key
		 * exist that is already part of the keys scheduled for insertion or if a
		 * key that is found that is not part of the keys from stage, then
		 * schedule that record for deletion.
		 *
		 * Records left in $stage_records is candidates for being updated. Go
		 * through them and see if they already exist in $prod_records.
		 */
		foreach ( $prod_records as $prod_key => $prod_record ) {
			if ( ! in_array( $prod_record['meta_key'], $stage_keys ) || in_array( $prod_record['meta_key'], $insert_keys ) ) {
				$delete[] = $prod_record;
				unset( $prod_records[$prod_key] );
			} else {
				foreach ( $stage_records as $stage_key => $stage_record ) {
					if ( $stage_record['meta_key'] == $prod_record['meta_key'] ) {
						$stage_record['user_id']  = $prod_record['user_id'];
						$stage_record['umeta_id'] = $prod_record['umeta_id'];
						$update[] = $stage_record;
						unset( $stage_records[$stage_key] );
						unset( $prod_records[$prod_key] );
					}
				}
			}
		}

		// Records left in $stage_records should be inserted.
		foreach ( $stage_records as $record ) {
			$insert[] = $record;
		}

		// Records left in $prod_records should be deleted.
		foreach ( $prod_records as $record ) {
			$delete[] = $record;
		}

		foreach ( $delete as $record ) {
			$this->delete_user_meta( $record['umeta_id'] );
		}

		foreach ( $insert as $record ) {
			$this->insert_user_meta( $record );
		}

		foreach ( $update as $record ) {
			$this->update_user_meta( $record );
		}
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->wpdb->users;
	}

	/**
	 * @return string
	 */
	protected function target_class() {
		return '\Me\Stenberg\Content\Staging\Models\User';
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

		$this->insert_all_user_meta( $obj );
	}

	/**
	 * @param array $raw
	 * @return User
	 */
	protected function do_create_object( array $raw ) {
		$obj = new User( $raw['ID'] );
		$obj->set_login( $raw['user_login'] );
		$obj->set_password( $raw['user_pass'] );
		$obj->set_nicename( $raw['user_nicename'] );
		$obj->set_email( $raw['user_email'] );
		$obj->set_url( $raw['user_url'] );
		$obj->set_registered( $raw['user_registered'] );
		$obj->set_activation_key( $raw['user_activation_key'] );
		$obj->set_status( $raw['user_status'] );
		$obj->set_display_name( $raw['display_name'] );
		$obj->set_meta( $this->get_user_meta( $obj->get_id() ) );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'user_login'          => $obj->get_login(),
			'user_pass'           => $obj->get_password(),
			'user_nicename'       => $obj->get_nicename(),
			'user_email'          => $obj->get_email(),
			'user_url'            => $obj->get_url(),
			'user_registered'     => $obj->get_registered(),
			'user_activation_key' => $obj->get_activation_key(),
			'user_status'         => $obj->get_status(),
			'display_name'        => $obj->get_display_name(),
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
			'%s', // user_login
			'%s', // user_pass
			'%s', // user_nicename
			'%s', // user_email
			'%s', // user_url
			'%s', // user_registered
			'%s', // user_activation_key
			'%d', // user_status
			'%s', // display_name
		);
	}

	/**
	 * @param int $user_id
	 */
	private function get_user_meta( $user_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->usermeta . ' WHERE user_id = %d',
			$user_id
		);

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * @param array $record
	 */
	private function insert_user_meta( array $record ) {
		$this->wpdb->insert(
			$this->wpdb->usermeta,
			array(
				'user_id'    => $record['user_id'],
				'meta_key'   => $record['meta_key'],
				'meta_value' => $record['meta_value'],
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * @param array $record
	 */
	private function update_user_meta( array $record ) {
		$this->wpdb->update(
			$this->wpdb->usermeta,
			array(
				'user_id'    => $record['user_id'],
				'meta_key'   => $record['meta_key'],
				'meta_value' => $record['meta_value'],
			),
			array( 'umeta_id' => $record['umeta_id'] ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $umeta_id
	 */
	private function delete_user_meta( $umeta_id ) {
		$this->wpdb->delete(
			$this->wpdb->usermeta,
			array( 'umeta_id' => $umeta_id ),
			array( '%d' )
		);
	}
}