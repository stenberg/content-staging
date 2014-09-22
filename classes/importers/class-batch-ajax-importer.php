<?php
namespace Me\Stenberg\Content\Staging\Importers;

use Me\Stenberg\Content\Staging\DB\Batch_Import_Job_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;

class Batch_AJAX_Importer extends Batch_Importer {

	/**
	 * Constructor.
	 *
	 * @param Batch_Import_Job $job
	 * @param Batch_Import_Job_DAO $job_dao
	 * @param Post_DAO $post_dao
	 * @param Postmeta_DAO $postmeta_dao
	 * @param Term_DAO $term_dao
	 * @param User_DAO $user_dao
	 */
	public function __construct( Batch_Import_Job $job, Batch_Import_Job_DAO $job_dao, Post_DAO $post_dao,
								 Postmeta_DAO $postmeta_dao, Term_DAO $term_dao, User_DAO $user_dao ) {
		parent::__construct( 'ajax', $job, $job_dao, $post_dao, $postmeta_dao, $term_dao, $user_dao );
	}

	/**
	 * Trigger importer.
	 */
	public function run() {

		// Make sure AJAX import has not already finished.
		if ( $this->job->get_status() > 1 ) {
			return;
		}

		// Get next post to import.

		// Import post.
	}

}