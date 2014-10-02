<?php
namespace Me\Stenberg\Content\Staging\Models;

abstract class Model {

	private $id;

	public function __construct( $id = null ) {
		$this->set_id( $id );
	}

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}

}