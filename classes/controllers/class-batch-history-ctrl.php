<?php
namespace Me\Stenberg\Content\Staging\Controllers;

use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\View\Batch_History_Table;
use Me\Stenberg\Content\Staging\View\Template;

class Batch_History_Ctrl {

	private $template;
	private $helper;

	public function __construct( Template $template ) {
		$this->template  = $template;
		$this->helper = Helper_Factory::get_instance();
	}

	public function init() {
		$order_by = 'post_modified';
		$order    = 'desc';
		$per_page = 10;
		$paged    = 1;

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

		$status  = array( 'draft' );
		$count   = $this->helper->get_dao( 'Batch' )->count( $status );
		$batches = $this->helper->get_dao( 'Batch' )->get_batches( $status, $order_by, $order, $per_page, $paged );

		foreach ( $batches as $batch ) {
			// Get IDs of posts user selected to include in this batch.
			$post_ids = $this->helper->get_dao( 'Batch' )->get_post_meta( $batch->get_id(), 'sme_selected_post_ids', true );

			if ( ! $post_ids ) {
				$post_ids = array();
			}

			$batch->set_posts( $this->helper->get_dao( 'Post' )->find_by_ids( $post_ids ) );
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