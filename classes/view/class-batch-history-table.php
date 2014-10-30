<?php
namespace Me\Stenberg\Content\Staging\View;

use Me\Stenberg\Content\Staging\Models\Batch;
use WP_List_Table;

class Batch_History_Table extends WP_List_Table {

	private $actions;

	public function __construct() {

		// Set parent defaults.
		parent::__construct( array(
			'singular'  => 'batch',
			'plural'    => 'batches',
			'ajax'      => false
		) );

		$this->actions = array();
	}

	/**
	 * Called if a column does not have a method that provides logic for
	 * rendering that column.
	 *
	 * @param Batch $item
	 * @param array $column_name
	 * @return string Text or HTML to be placed inside the column.
	 */
	public function column_default( Batch $item, $column_name ) {
		switch( $column_name ) {
			case 'post_modified':
				return $item->get_modified();
			case 'post_author':
				return $item->get_creator()->get_display_name();
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
		return sprintf(
			'<strong>%s</strong>',
			$item->get_title()
		);
	}

	public function column_sme_batch_history( Batch $item ) {
		$str = '';

		if ( count( $item->get_posts() ) > 0 ) {
			foreach ( $item->get_posts() as $post ) {
				$str .= '<p>' . $post->get_title() . '</p>';
			}
		}

		return $str;
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
			'post_title'        => 'Batch Title',
			'post_modified'     => 'Modified',
			'post_author'       => 'Created By',
			'sme_batch_history' => 'Posts',
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
	 * Handle bulk actions.
	 *
	 * @see $this->prepare_items()
	 */
	public function process_bulk_action() {

		// Detect when a bulk action is being triggered.
		if ( 'delete' === $this->current_action() ) {
			wp_die( 'Batches deleted!' );
		}

	}

	/**
	 * Prepare batches for being displayed.
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();
	}

}