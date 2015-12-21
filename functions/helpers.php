<?php
use Me\Stenberg\Content\Staging\Apis\Common_API;

/**
 * Get a post by its global unique identifier (GUID).
 *
 * @param string $guid
 *
 * @return object A post object.
 */
function sme_get_post_by_guid( $guid ) {

	/**
	 * @var Common_API $sme_content_staging_api
	 */
	global $sme_content_staging_api;

	return $sme_content_staging_api->get_post_by_guid( $guid );
}

/**
 * Check if we are currently on Content Stage or Production.
 *
 * @return bool
 */
function sme_is_content_stage() {

	/**
	 * @var Common_API $sme_content_staging_api
	 */
	global $sme_content_staging_api;

	return $sme_content_staging_api->is_content_stage();
}
