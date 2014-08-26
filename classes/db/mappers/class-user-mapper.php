<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

use Me\Stenberg\Content\Staging\Models\User;

class User_Mapper extends Mapper {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing here atm.
	}

	public function array_to_user_object( $array ) {

		$object = null;

		/*
		 * If array has been populated with values, map these values to a new
		 * User object.
		 */
		if ( ! empty( $array ) ) {
			$object = $this->array_to_object( $array, new User() );
		}

		return $object;
	}

}