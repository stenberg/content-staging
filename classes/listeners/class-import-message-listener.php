<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch_Import_Job;
use Me\Stenberg\Content\Staging\Models\Post;

class Import_Message_Listener {

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Data access objects.
		$this->post_dao = Helper_Factory::get_instance()->get_dao( 'Post' );

		// Register listeners.
		add_action( 'sme_verify_batch_parent_post_missing', array( $this, 'verify_batch_parent_post_missing' ), 10, 2 );
		add_action( 'sme_prepared', array( $this, 'prepared' ) );
		add_action( 'sme_import', array( $this, 'import' ) );
		add_action( 'sme_batch_import_job_creation_failure', array( $this, 'batch_import_job_creation_failure' ) );
		add_action( 'sme_batch_import_job_created', array( $this, 'batch_import_job_created' ) );
		add_action( 'sme_batch_import_startup_failure', array( $this, 'batch_import_startup_failure' ) );
		add_action( 'sme_attachment_import_failure', array( $this, 'attachment_import_failure' ), 10, 3 );
		add_action( 'sme_unauthorized_batch_import', array( $this, 'unauthorized_batch_import' ) );
		add_action( 'sme_post_imported', array( $this, 'post_imported' ), 10, 2 );
		add_action( 'sme_imported', array( $this, 'imported' ) );
	}

	/**
	 * A post in the batch is missing its parent post.
	 *
	 * @param Post $post
	 * @param Batch_Import_Job $job
	 */
	public function verify_batch_parent_post_missing( Post $post, Batch_Import_Job $job ) {
		$this->add_message(
			sprintf(
				'Post <a href="%s" target="_blank">%s</a> has a parent post that does not exist on production and is not part of this batch. Include post <a href="%s" target="_blank">%s</a> in this batch to resolve this issue.',
				$job->get_batch()->get_backend() . 'post.php?post=' . $post->get_id() . '&action=edit',
				$post->get_title(),
				$job->get_batch()->get_backend() . 'post.php?post=' . $post->get_parent()->get_id() . '&action=edit',
				$post->get_parent()->get_title()
			),
			'error',
			$job
		);
	}

	/**
	 * Batch prepared.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function prepared( Batch_Import_Job $job ) {
		if ( $job->get_status() !== 2 ) {
			$this->add_message( 'Pre-flight successful!', 'success', $job );
		}
	}

	/**
	 * Start batch import.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function import( Batch_Import_Job $job ) {
		$this->add_message( 'Starting batch import...', 'info', $job );
	}

	/**
	 * Failed to create batch import job.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function batch_import_job_creation_failure( Batch_Import_Job $job ) {
		$this->add_message( 'Failed creating import job.', 'error', $job );
	}

	/**
	 * @param Batch_Import_Job $job
	 */
	public function batch_import_job_created( Batch_Import_Job $job ) {
		$this->add_message(
			sprintf(
				'Created import job ID: <span id="sme-batch-import-job-id">%s</span>',
				$job->get_id()
			),
			'info',
			$job
		);
	}

	/**
	 * Batch import failed to start.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function batch_import_startup_failure( Batch_Import_Job $job ) {
		$this->add_message( 'Batch import failed to start.', 'info', $job );
	}

	/**
	 * Attempt to import a batch has been terminated.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function unauthorized_batch_import( Batch_Import_Job $job ) {
		$this->add_message( __( 'Something went wrong', 'sme-content-staging' ), 'error', $job );
	}

	/**
	 * Failed to import an attachment.
	 *
	 * @param array $attachment
	 * @param string $filepath
	 * @param Batch_Import_Job $job
	 */
	public function import_attachment_failure( array $attachment, $filepath, Batch_Import_Job $job ) {

		$failed_attachment = '';

		if ( isset( $attachment['items'][0] ) ) {
			$failed_attachment = sprintf(
				' Attachment %s and generated sizes could not be deployed to production. This is most likely a file permission error, make sure your web server can write to the image upload directory.',
				$attachment['items'][0]
			);
		}

		$message = sprintf(
			'Failed creating directory %s.%s',
			$filepath,
			$failed_attachment
		);

		$this->add_message( $message, 'warning', $job );
	}

	/**
	 * Post has just been imported.
	 *
	 * @param Post $post
	 * @param Batch_Import_Job $job
	 */
	public function post_imported( Post $post, Batch_Import_Job $job ) {
		$this->add_message(
			sprintf(
				'Post <strong>%s</strong> has been successfully imported.',
				$post->get_title()
			),
			'success',
			$job
		);
	}

	/**
	 * Batch has been successfully imported.
	 *
	 * @param Batch_Import_Job $job
	 */
	public function imported( Batch_Import_Job $job ) {

		$links  = array();
		$output = '';

		// Get diffs from database.
		$diffs = $this->post_dao->get_post_diffs( $job );

		foreach ( $diffs as $post ) {
			$links[] = array(
				'link'  => get_permalink( $post->get_prod_id() ),
				'title' => get_the_title( $post->get_prod_id() ),
			);
		}

		$links = apply_filters( 'sme_imported_post_links', $links );

		foreach ( $links as $link ) {
			$output .= '<li><a href="' . $link['link'] . '" target="_blank">' . $link['title'] . '</a></li>';
		}

		if ( $output !== '' ) {
			$output = '<ul>' . $output . '</ul>';
			$this->add_message( '<h3>Posts deployed to the live site:</h3>' . $output, 'info', $job );
		}

		$this->add_message( 'Batch has been successfully imported!', 'success', $job );
	}

	/**
	 * @param string $message
	 * @param string $level
	 * @param Batch_Import_Job $job
	 */
	private function add_message( $message, $level, Batch_Import_Job $job ) {
		$meta = array(
			'message' => $message,
			'level'   => $level,
		);

		if ( $job->get_id() ) {
			add_post_meta( $job->get_id(), 'sme_import_message', $meta );
		}

		$job->add_message( $message, $level );
	}
}