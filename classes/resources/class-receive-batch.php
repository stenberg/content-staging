<?php
namespace Me\Stenberg\Content\Staging\Resources;

use Me\Stenberg\Content\Staging\Background_Process;
use Me\Stenberg\Content\Staging\DB\Batch_Importer_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Batch_Importer;
use Me\Stenberg\Patterns\Observer\Observable;
use Me\Stenberg\Patterns\Observer\Observer;

class Receive_Batch implements Observer {

	private $batch_importer_dao;
	private $post_dao;

	/**
	 * Construct object, dependencies are injected.
	 *
	 * @param Batch_Importer_DAO $batch_importer_dao
	 * @param Post_DAO $post_dao
	 */
	public function __construct( Batch_Importer_DAO $batch_importer_dao, Post_DAO $post_dao ) {
		$this->batch_importer_dao = $batch_importer_dao;
		$this->post_dao           = $post_dao;
	}

	/**
	 * Called by an Observable object whenever data has been updated.
	 *
	 * @param Observable $xmlrpc_client
	 * @return string
	 */
	public function update( Observable $xmlrpc_client ) {

		// Get incoming request data.
		$data = $xmlrpc_client->get_request_data();

		// Check if a batch has been provided.
		if ( ! isset( $data['batch'] ) || ! ( $data['batch'] instanceof Batch ) ) {
			return array(
				'error' => array(
					'Invalid batch!',
				),
			);
		}

		// Get batch.
		$batch = $data['batch'];

		// Make sure an action has been provided.
		if ( ! isset( $data['action'] ) ) {
			return array( 'error' => array( $_SERVER['HTTP_HOST'] . ': No action provided!' ) );
		}

		if ( $data['action'] === 'preflight' ) {
			$status = $this->receive_preflight_data( $batch );
			if ( ! $status ) {
				return array( 'success' => array( 'Pre-flight successful!' ) );
			} else {
				return $status;
			}
		} else if ( $data['action'] === 'send' ) {

			$importer = new Batch_Importer();
			$importer->set_batch( $batch );
			$this->batch_importer_dao->insert_importer( $importer );

			// Trigger import script.
			$import_script = dirname( dirname( dirname( __FILE__ ) ) ) . '/scripts/import-batch.php';
			$background_process = new Background_Process(
				'php ' . $import_script . ' ' . ABSPATH . ' ' . get_site_url() . ' ' . $importer->get_id()
			);

			if ( file_exists( $import_script ) ) {
				$background_process->run();
			}

			error_log( 'Background Process ID: ' . $background_process->get_pid() );

			// Return batch ID.
			return array(
				'info' => array( 'Import of batch has been started. Importer ID: ' . $importer->get_id() )
			);

		} else {
			return array( 'error' => array( $_SERVER['HTTP_HOST'] . ': Invalid action provided!' ) );
		}
	}

	/**
	 * Runs on the production server when pre-flight data is received.
	 *
	 * @param Batch $batch
	 * @return array
	 */
	private function receive_preflight_data( Batch $batch ) {

		$messages = array();

		foreach ( $batch->get_posts() as $post ) {

			// Check if parent post exist on production or in batch.
			if ( ! $this->parent_post_exists( $post, $batch->get_posts() ) ) {
				$messages['error'][] = 'Post ID ' . $post->get_id() . ' is missing its parent post (ID ' . $post->get_post_parent() . '). Parent post does not exist on production and is not part of this batch';
			}
		}

		foreach ( $batch->get_attachments() as $attachment ) {

			foreach ( $attachment['sizes'] as $size ) {

				// Check if attachment exists on content stage.
				if ( ! $this->attachment_exists( $size) ) {
					$messages['warning'][] = 'Attachment <a href="' . $size . '" target="_blank">' . $size . '</a> is missing on content stage and will not be deployed to production.';
				}
			}
		}

		return $messages;
	}

	/**
	 * Make sure parent post exist (if post has any) either in production
	 * database or in batch.
	 *
	 * @param array $post
	 * @param array $posts
	 * @return bool True if parent post exist (or post does not have a parent), false
	 *              otherwise.
	 */
	private function parent_post_exists( $post, $posts ) {

		// Check if the post has a parent post.
		if ( $post->get_post_parent() <= 0 ) {
			return true;
		}

		// Check if parent post exist on production server.
		if ( $this->post_dao->get_post_by_guid( $post->get_post_parent_guid() ) ) {
			return true;
		}

		// Parent post is not on production, look in this batch for parent post.
		foreach ( $posts as $item ) {
			if ( $item->get_id() == $post->get_post_parent() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an attachment exists on remote server.
	 *
	 * @param string $attachment
	 * @return bool
	 */
	private function attachment_exists( $attachment ) {
		$ch = curl_init( $attachment );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close($ch);

		if ( $code == 200 ) {
			return true;
		}

		return false;
	}

}
