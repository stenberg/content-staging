<?php
namespace Me\Stenberg\Content\Staging\DB\Mappers;

class Mapper {

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Nothing here atm.
	}

	protected function array_to_object( $array, $object ) {

		/*
		 * Loop through array. Array keys are assumed to contain the field name
		 * from database table and array values the field value.
		 */
		foreach ( $array as $key => $value ) {

			/*
			 * It is assumed that the object we want to map the array to contains
			 * setter methods for each property. Furthermore it is assumed the
			 * setters are all lower case and each setter corresponds to a field from
			 * the database table.
			 */
			$method = 'set_' . strtolower( $key );

			// Check that the method actually exist for provided object.
			if ( method_exists( $object, $method ) ) {

				// Call the setter method and provide the field value as input.
				call_user_func( array( $object, $method ), $value );
			}
		}

		return $object;
	}
}