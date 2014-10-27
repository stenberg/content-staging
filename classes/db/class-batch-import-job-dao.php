<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;
use Me\Stenberg\Content\Staging\Models\Model;

class Batch_Import_Job_DAO extends DAO {

	private $table;

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
		$this->table = $wpdb->posts;
	}

	/**
	 * @param Batch_Import_Job $job
	 */
	public function update_job( Batch_Import_Job $job ) {
		$job->set_modified( current_time( 'mysql' ) );
		$job->set_modified_gmt( current_time( 'mysql', 1 ) );

		$data         = $this->create_array( $job );
		$where        = array( 'ID' => $job->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );

		$this->update( $data, $where, $format, $where_format );

		$this->update_post_meta( $job->get_id(), 'sme_import_messages', $job->get_messages() );
		$this->update_post_meta( $job->get_id(), 'sme_import_status', $job->get_status() );
		$this->update_post_meta( $job->get_id(), 'sme_import_key', $job->get_key() );
	}

	/**
	 * Delete provided importer.
	 *
	 * Set 'post_status' for provided importer to 'draft'. This will hide the
	 * import job from users, but keep it for future references.
	 *
	 * Empty 'post_content'. Since import jobs can store huge batches in the
	 * 'post_content' field this is just a precaution so we do not fill the
	 * users database with a lot of unnecessary data.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function delete_job( Batch_Import_Job $job ) {
		$this->update(
			array(
				'post_content' => '',
				'post_status'  => 'draft',
			),
			array(
				'ID' => $job->get_id(),
			),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @return string
	 */
	protected function get_table() {
		return $this->table;
	}

	/**
	 * @return string
	 */
	protected function target_class() {
		return '\Me\Stenberg\Content\Staging\Models\Batch_Import_Job';
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
		return 'SELECT * FROM ' . $this->table . ' WHERE ID = %d';
	}

	/**
	 * @param array $ids
	 * @return string
	 */
	protected function select_by_ids_stmt( array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return 'SELECT * FROM ' . $this->table . ' WHERE ID in (' . $placeholders . ')';
	}

	/**
	 * @param Model $obj
	 */
	protected function do_insert( Model $obj ) {
		$obj->set_date( current_time( 'mysql' ) );
		$obj->set_date_gmt( current_time( 'mysql', 1 ) );
		$obj->set_modified( $obj->get_date() );
		$obj->set_modified_gmt( $obj->get_date_gmt() );

		$data   = $this->create_array( $obj );
		$format = $this->format();
		$this->wpdb->insert( $this->table, $data, $format );
		$obj->set_id( $this->wpdb->insert_id );

		// Create a key needed to run this import job.
		$obj->generate_key();
		$this->update_post_meta( $obj->get_id(), 'sme_import_key', $obj->get_key() );
	}

	/**
	 * @param array $raw
	 * @return Batch_Import_Job
	 */
	protected function do_create_object( array $raw ) {
		$obj   = new Batch_Import_Job( $raw['ID'] );
		$batch = unserialize( base64_decode( $raw['post_content'] ) );
		if ( $batch instanceof Batch ) {
			$obj->set_batch( $batch );
		}
		$obj->set_creator_id( $raw['post_author'] );
		$obj->set_date( $raw['post_date'] );
		$obj->set_date_gmt( $raw['post_date_gmt'] );
		$obj->set_modified( $raw['post_modified'] );
		$obj->set_modified_gmt( $raw['post_modified_gmt'] );
		$this->get_job_meta( $obj );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'post_author'       => $obj->get_creator_id(),
			'post_date'         => $obj->get_date(),
			'post_date_gmt'     => $obj->get_date_gmt(),
			'post_content'      => base64_encode( serialize( $obj->get_batch() ) ),
			'post_status'       => 'publish',
			'comment_status'    => 'closed',
			'ping_status'       => 'closed',
			'post_modified'     => $obj->get_modified(),
			'post_modified_gmt' => $obj->get_modified_gmt(),
			'post_type'         => 'sme_batch_import_job',
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
			'%s', // post_status
			'%s', // comment_status
			'%s', // ping_status
			'%s', // post_modified
			'%s', // post_modified_gmt
			'%s', // post_type
		);
	}

	/**
	 * @param Batch_Import_Job $job
	 */
	private function get_job_meta( Batch_Import_Job $job ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->postmeta . ' WHERE post_id = %d',
			$job->get_id()
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $record ) {
			if ( $record['meta_key'] == 'sme_import_messages' ) {
				$job->set_messages( unserialize( $record['meta_value'] ) );
			}

			if ( $record['meta_key'] == 'sme_import_status' ) {
				$job->set_status( $record['meta_value'] );
			}

			if ( $record['meta_key'] == 'sme_import_key' ) {
				$job->set_key( $record['meta_value'] );
			}
		}
	}

}
