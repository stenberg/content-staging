<?php
namespace Me\Stenberg\Content\Staging;

use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;

class API {

	private $post_dao;
	private $postmeta_dao;

	public function __construct( Post_DAO $post_dao, Postmeta_DAO $postmeta_dao ) {
		$this->post_dao = $post_dao;
		$this->postmeta_dao = $postmeta_dao;
	}

	public function get_post_by_guid( $guid ) {
		return $this->post_dao->get_post_by_guid( $guid );
	}
}