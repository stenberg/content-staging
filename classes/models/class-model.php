<?php
namespace Me\Stenberg\Content\Staging\Models;

use Me\Stenberg\Content\Staging\Object_Watcher;

abstract class Model {

	private $id;

	public function __construct( $id = null ) {
		$this->id = $id;
	}

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}

	/**
	 * @deprecated
	 */
	public function mark_new() {
		Object_Watcher::add_new( $this );
	}

	/**
	 * @deprecated
	 */
	public function mark_deleted() {
		Object_Watcher::add_delete( $this );
	}

	/**
	 * @deprecated
	 */
	public function mark_dirty() {
		Object_Watcher::add_dirty( $this );
	}

	/**
	 * @deprecated
	 */
	public function mark_clean() {
		Object_Watcher::add_clean( $this );
	}

}