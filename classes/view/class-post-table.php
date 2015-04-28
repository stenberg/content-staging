<?php
namespace Me\Stenberg\Content\Staging\View;

use Me\Stenberg\Content\Staging\Models\Batch;
use Me\Stenberg\Content\Staging\Models\Post;
use WP_List_Table;

class Post_Table extends WP_List_Table {

	/**
	 * @var Batch
	 */
	private $batch;

	/**
	 * @var array
	 */
	private $custom_items;

	/**
	 * @param Batch $batch
	 */
	public function __construct( Batch $batch ) {

		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'post',
				'plural'   => 'posts',
				'ajax'     => false,
			)
		);

		$this->batch = $batch;
	}

	/**
	 * @return Batch
	 */
	public function get_batch() {
		return $this->batch;
	}

	/**
	 * Called if a column does not have a method that provides logic for
	 * rendering that column.
	 *
	 * @param Post $post
	 * @param array $column_name
	 * @return string Text or HTML to be placed inside the column.
	 */
	public function column_default( $post, $column_name ) {
		switch ( $column_name ) {
			case 'post_title':
				$value = $this->column_title( $post );
				break;
			case 'post_modified':
				$value = call_user_func( array( $post, 'get_modified' ) );
				break;
			default:
				$value = '';
		}

		return apply_filters( 'sme_edit_batch_column_value', $value, $column_name, $post );
	}

	public function column_title( Post $post ) {
		$parents = '';

		if ( $post->get_parent() !== null ) {
			$parents = $this->get_parent_title( $post->get_parent(), $parents );
		}

		return sprintf(
			'%s<strong><span class="row-title"><a href="%s" target="_blank">%s</a></span></strong>',
			$parents,
			get_edit_post_link( $post->get_id() ),
			$post->get_title()
		);
	}

	public function get_parent_title( Post $post, $content = '' ) {
		$content = $post->get_title() . ' | ' . $content;
		if ( $post->get_parent() !== null ) {
			$content = $this->get_parent_title( $post->get_parent(), $content );
		}
		return $content;
	}

	/**
	 * Display checkbox (e.g. for bulk actions). The checkbox should have the
	 * value of the post ID.
	 *
	 * @param Post $post
	 * @return string Text to be placed inside the column.
	 */
	public function column_cb( $post ) {
		return sprintf(
			'<input type="checkbox" id="sme_select_post_%s" class="sme-select-post" name="%s[]" value="%s"/>',
			$post->get_id(),
			$this->_args['plural'],
			$post->get_id()
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
		$columns = array( 'cb' => '<input type="checkbox" />' );
		foreach ( $this->custom_items as $key => $item ) {
			$columns[ $key ] = $item['title'];
		}
		return $columns;
	}

	/**
	 * Make columns sortable.
	 *
	 * @return array An associative array containing sortable columns:
	 * Key = Column name
	 * Value = array( value from database (most likely), bool )
	 */
	public function get_sortable_columns() {
		$columns = array();
		foreach ( $this->custom_items as $key => $item ) {
			if ( true === $item['sortable'] ) {
				$columns[ $key ] = array( $item['sort_by'], $item['pre_sorted'] );
			}
		}
		return $columns;
	}

	/**
	 * Prepare posts for being displayed.
	 */
	public function prepare_items() {
		$this->custom_items = array(
			'post_title' => array(
				'title'      => 'Post Title',
				'sortable'   => true,
				'sort_by'    => 'post_title',
				'pre_sorted' => false,
			),
			'post_modified' => array(
				'title'      => 'Modified',
				'sortable'   => true,
				'sort_by'    => 'post_modified',
				'pre_sorted' => false,
			),
		);
		$this->custom_items    = apply_filters( 'sme_edit_batch_columns', $this->custom_items );
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
	}

	public function extra_tablenav( $which ) {
		do_action( 'sme_edit_batch_extra_tablenav', $which );
	}

}