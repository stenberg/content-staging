<?php
namespace Me\Stenberg\Content\Staging\View;

use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\Helper_Factory;
use Me\Stenberg\Content\Staging\Models\Batch;
use WP_List_Table;

class Batch_Table extends WP_List_Table {

	private $actions;

	public function __construct() {

		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'batch',
				'plural'   => 'batches',
				'ajax'     => false,
			)
		);

		$this->actions = array();
	}

	/**
	 * Called if a column does not have a method that provides logic for
	 * rendering that column.
	 *
	 * @param Batch $batch
	 * @param array $column_name
	 * @return string Text or HTML to be placed inside the column.
	 */
	public function column_default( $batch, $column_name ) {

		// Display name of user who created the batch.
		$display_name = '';

		if ( $batch->get_creator() !== null ) {
			$display_name = $batch->get_creator()->get_display_name();
		}

		switch ( $column_name ) {
			case 'post_modified':
				return $batch->get_modified();
			case 'post_author':
				return $display_name;
			default:
				return '';
		}
	}

	/**
	 * Render the 'post_title' column.
	 *
	 * @param Batch $item
	 * @return string HTML to be rendered inside column.
	 */
	public function column_post_title( Batch $item ){

		$edit_link   = admin_url( 'admin.php?page=sme-edit-batch&id=' . $item->get_id() );
		$delete_link = admin_url( 'admin.php?page=sme-delete-batch&id=' . $item->get_id() );

		// Build row actions
		$actions = array(
			'edit'   => '<a href="' . $edit_link . '">Edit</a>',
			'delete' => '<a href="' . $delete_link . '">Delete</a>',
		);

		// Return the title contents.
		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			$edit_link,
			$item->get_title(),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Display checkbox (e.g. for bulk actions). The checkbox should have the
	 * value of the batch ID.
	 *
	 * @param Batch $batch
	 *
	 * @return string Text to be placed inside the column.
	 */
	public function column_cb( $batch ) {
		return sprintf(
			'<input type="checkbox" id="sme_select_batch_%s" class="sme-select-batch" name="%s[]" value="%s"/>',
			$batch->get_id(),
			$this->_args['plural'],
			$batch->get_id()
		);
	}

	/**
	 * Set the table's columns and titles.
	 *
	 * The column named 'cb' will display checkboxes. Make sure to create a
	 * column_cb method for setting up the checkbox column.
	 *
	 * @return array An associative array:
	 * Key = Column name
	 * Value = Column title (except for key 'cb')
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'post_title'    => 'Batch Title',
			'post_modified' => 'Modified',
			'post_author'   => 'Created By',
		);
	}

	/**
	 * Make columns sortable.
	 *
	 * @return array An associative array containing sortable columns:
	 * Key = Column name
	 * Value = array( value from database (most likely), bool )
	 */
	public function get_sortable_columns() {
		return array(
			'post_title'    => array( 'post_title', false ),
			'post_modified' => array( 'post_modified', false ),
			'post_author'   => array( 'post_author', false ),
		);
	}

	/**
	 * Set bulk actions.
	 *
	 * @param array $actions
	 */
	public function set_bulk_actions( array $actions ) {
		$this->actions = $actions;
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array An associative array containing bulk actions:
	 * Key: Bulk action slug
	 * Value: Bulk action title
	 */
	public function get_bulk_actions() {
		return $this->actions;
	}

	/**
	 * Prepare batches for being displayed.
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );
	}

}