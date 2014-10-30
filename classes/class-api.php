<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;

class API {

	private $post_dao;
	private $postmeta_dao;

	public function __construct() {
		$this->post_dao     = Helper_Factory::get_instance()->get_dao( 'Post' );
		$this->postmeta_dao = Helper_Factory::get_instance()->get_dao( 'Postmeta' );
	}

	public function get_post_by_guid( $guid ) {
		return $this->post_dao->get_by_guid( $guid );
	}
}