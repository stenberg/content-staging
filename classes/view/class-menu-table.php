<?php
namespace SonyMobile\Content\Staging\View;

use stdClass;
use WP_List_Table;

class Menu_Table extends WP_List_Table {

	/**
	 * @var array
	 */
	private $selected_items;

	public function __construct( array $selected_items ) {

		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'menu',
				'plural'   => 'menus',
				'ajax'     => false,
			)
		);

		$this->selected_items = $selected_items;
	}

	/**
	 * Called if a column does not have a method that provides logic for
	 * rendering of that column.
	 *
	 * @param stdClass $menu
	 * @param array $column_name
	 * @return string Text or HTML to be placed inside the column.
	 */
	public function column_default( $menu, $column_name ) {
		return '';
	}

	/**
	 * Render the menu name column.
	 *
	 * @param stdClass $menu
	 * @return string HTML to be rendered inside column.
	 */
	public function column_menu_name( $menu ){
		return sprintf(
			'<label for="sme_select_menu_%s"><strong><span class="row-title">%s</span></strong></label>',
			$menu->term_taxonomy_id,
			$menu->name
		);
	}

	/**
	 * Display checkbox (e.g. for bulk actions). The checkbox should have the
	 * value of the menu ID.
	 *
	 * @param stdClass $menu
	 * @return string Text to be placed inside the column.
	 */
	public function column_cb( $menu ) {

		$checked = in_array( $menu->term_taxonomy_id, $this->selected_items ) ? 'checked="checked"' : '';

		return sprintf(
			'<input type="checkbox" id="sme_select_menu_%s" class="sme-select-menu" name="%s[]" value="%s" %s>',
			$menu->term_taxonomy_id,
			$this->_args['plural'],
			$menu->term_taxonomy_id,
			$checked
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
			'cb'        => '<input type="checkbox" />',
			'menu_name' => 'Menu',
		);
	}

	/**
	 * Prepare sites for display.
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );
	}

}