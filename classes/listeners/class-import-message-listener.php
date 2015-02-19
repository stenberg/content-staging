<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;

class Import_Message_Listener {

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * @var Common_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->post_dao = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->api      = Helper_Factory::get_instance()->get_api( 'Common' );

		// Register listeners.
		add_action( 'sme_post_imported', array( $this, 'post_imported' ), 10, 2 );
		add_action( 'sme_imported', array( $this, 'imported' ) );
	}

	/**
	 * Post has just been imported.
	 *
	 * @param Post  $post
	 * @param Batch $batch
	 */
	public function post_imported( Post $post, Batch $batch ) {

		$message = sprintf(
			'Post <strong>%s</strong> has been successfully imported.',
			$post->get_title()
		);

		$this->api->add_deploy_message( $batch->get_id(), $message, 'success', 103 );
	}

	/**
	 * Batch has been successfully imported.
	 *
	 * @param Batch $batch
	 */
	public function imported( Batch $batch ) {

		$links  = array();
		$output = '';
		$posts  = array();
		$types  = array( 'page', 'post' );

		// Get diffs from database.
		$diffs = $this->post_dao->get_post_diffs( $batch );

		// Get all posts.
		foreach ( $diffs as $diff ) {
			$posts[] = get_post( $diff->get_prod_id() );
		}

		// Only keep published posts of type $types.
		$posts = array_filter(
			$posts,
			function( $post ) use ( $types ) {
				return ( $post->post_status == 'publish' && in_array( $post->post_type, $types ) );
			}
		);

		// Create links for each of the posts.
		foreach ( $posts as $post ) {
			$links[] = array(
				'link'  => get_permalink( $post->ID ),
				'title' => $post->post_title,
			);
		}

		$links = apply_filters( 'sme_imported_post_links', $links );

		foreach ( $links as $link ) {
			$output .= '<li><a href="' . $link['link'] . '" target="_blank">' . $link['title'] . '</a></li>';
		}

		if ( $output !== '' ) {
			$output  = '<ul>' . $output . '</ul>';
			$message = '<h3>Posts deployed to the live site:</h3>' . $output;

			$this->api->add_deploy_message( $batch->get_id(), $message, 'info', 102 );
		}

		$this->api->add_deploy_message( $batch->get_id(), 'Batch has been successfully imported!', 'success', 101 );
	}

}