<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Exception;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\View\Batch_History_Table;
use Me\Stenberg\Content\Staging\View\Template;

class Batch_History_Ctrl {

	/**
	 * @var Template
	 */
	private $template;

	/**
	 * @var Batch_DAO
	 */
	private $batch_dao;

	/**
	 * @var Post_DAO
	 */
	private $post_dao;

	/**
	 * @param Template $template
	 */
	public function __construct( Template $template ) {
		$this->template  = $template;
		$this->batch_dao = Helper_Factory::get_instance()->get_dao( 'Batch' );
		$this->post_dao  = Helper_Factory::get_instance()->get_dao( 'Post' );
	}

	public function init() {

		$order_by = 'post_modified';
		$order    = 'desc';
		$per_page = 10;
		$paged    = 1;
		$status   = array( 'draft' );
		$posts    = array();

		if ( isset( $_GET['orderby'] ) ) {
			$order_by = $_GET['orderby'];
		}

		if ( isset( $_GET['order'] ) ) {
			$order = $_GET['order'];
		}

		if ( isset( $_GET['per_page'] ) ) {
			$per_page = $_GET['per_page'];
		}

		if ( isset( $_GET['paged'] ) ) {
			$paged = $_GET['paged'];
		}

		$count   = $this->batch_dao->count( $status );
		$batches = $this->batch_dao->get_batches( $status, $order_by, $order, $per_page, $paged );

		foreach ( $batches as $batch ) {
			// Get IDs of posts user selected to include in this batch.
			$post_ids = $this->batch_dao->get_post_meta( $batch->get_id(), 'sme_selected_post' );

			if ( ! is_array( $post_ids ) ) {
				$post_ids = array();
			}

			$posts = $this->post_dao->find_by_ids( $post_ids );

			$batch->set_posts( $posts );
		}

		// Prepare table of batches.
		$table        = new Batch_History_Table();
		$table->items = $batches;

		$table->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
			)
		);
		$table->prepare_items();

		$data = array(
			'table' => $table,
		);

		$this->template->render( 'batch-history', $data );
	}
}