<?php
namespace Me\Stenberg\Content\Staging\Listeners;

use Me\Stenberg\Content\Staging\Apis\Common_API;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Factories\DAO_Factory;
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
	public function __construct( Common_API $api, DAO_Factory $dao_factory ) {

		$this->api      = $api;
		$this->post_dao = $dao_factory->create( 'Post' );

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
		$types  = array( 'page', 'post' );

		// Only keep published posts of type $types.
		$posts = array_filter(
			$batch->get_posts(),
			function( Post $post ) use ( $types ) {
				return ( $post->get_post_status() == 'publish' && in_array( $post->get_type(), $types ) );
			}
		);

		// Create links for each of the posts.
		foreach ( $posts as $post ) {

			$post_id = $this->post_dao->get_id_by_guid( $post->get_guid() );

			$links[] = array(
				'link'  => get_permalink( $post_id ),
				'title' => $post->get_title(),
			);
		}

		$links = apply_filters( 'sme_imported_post_links', $links );

		foreach ( $links as $link ) {
			$output .= '<li><a href="' . $link['link'] . '" target="_blank">' . $link['title'] . '</a></li>';
		}

		if ( $output !== '' ) {
			$output  = '<ul>' . $output . '</ul>';
			$message = '<h3>Posts deployed to ' . get_bloginfo( 'name' ) . ':</h3>' . $output;

			$this->api->add_deploy_message( $batch->get_id(), $message, 'info', 102 );
		}

		$this->api->add_deploy_message( $batch->get_id(), 'Batch has been successfully imported!', 'success', 101 );
	}

}