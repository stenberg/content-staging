<?php

/**
 * Get a post by its global unique identifier (GUID).
 *
 * @param string $guid
 * @return object A post object.
 */
function sme_get_post_by_guid( $guid ) {
	global $sme_content_staging_api;
	return $sme_content_staging_api->get_post_by_guid( $guid );
}
