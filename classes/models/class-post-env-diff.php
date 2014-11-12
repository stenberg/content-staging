<?php
namespace Me\Stenberg\Content\Staging\Models;

/**
 * Class Post_Env_Diff
 *
 * Describes differences between a post on the staging environment and
 * the same post on the production environment.
 *
 * @package Me\Stenberg\Content\Staging\Models
 */
class Post_Env_Diff extends Model {

	private $post;
	private $stage_id;
	private $prod_id;
	private $stage_status;

	public function __construct( Post $post ) {
		parent::__construct();
		$this->post = $post;
	}

	/**
	 * @return Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * @param int $id
	 */
	public function set_stage_id( $id ) {
		$this->stage_id = (int) $id;
	}

	/**
	 * @return int
	 */
	public function get_stage_id() {
		return $this->stage_id;
	}

	/**
	 * @param int $id
	 */
	public function set_prod_id( $id ) {
		$this->prod_id = (int) $id;
	}

	/**
	 * @return int
	 */
	public function get_prod_id() {
		return $this->prod_id;
	}

	/**
	 * @param string $status
	 */
	public function set_stage_status( $status ) {
		$this->stage_status = $status;
	}

	/**
	 * @return string
	 */
	public function get_stage_status() {
		return $this->stage_status;
	}

}