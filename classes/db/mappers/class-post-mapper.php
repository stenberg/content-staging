<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

use Me\Stenberg\Content\Staging\Models\Post;

class Post_Mapper extends Mapper {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing here atm.
	}

	public function array_to_post_object( $array ) {

		$object = null;

		/*
		 * If array has been populated with values, map these values to a new
		 * Post object.
		 */
		if ( ! empty( $array ) ) {
			$object = $this->array_to_object( $array, new Post() );
		}

		return $object;
	}

}