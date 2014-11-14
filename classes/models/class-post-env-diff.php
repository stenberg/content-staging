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

	/**
	 * @var Post The post received from content staging. Will be manipulated in client
	 *           code, differences we need to keep track on should be assigned to
	 *           specific properties of this class.
	 */
	private $post;

	/**
	 * @var Post If the post already exist on production, then store the current
	 *           revision of the post in this property. Will not be set if the post is
	 *           new to production.
	 */
	private $revision;

	/**
	 * @var int Post ID on staging environment.
	 */
	private $stage_id;

	/**
	 * @var string Post status on staging environment.
	 */
	private $stage_status;

	/**
	 * @param Post $post
	 */
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
	 * @param Post $post
	 */
	public function set_revision( Post $post ) {
		$this->revision = $post;
	}

	/**
	 * @return Post
	 */
	public function get_revision() {
		return $this->revision;
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

	/**
	 * Get array representation of this object.
	 *
	 * @return array
	 */
	public function to_array() {
		$array = array(
			'prod_id'      => $this->get_post()->get_id(),
			'stage_id'     => $this->get_stage_id(),
			'stage_status' => $this->get_stage_status(),
		);

		if ( $this->get_revision() !== null ) {
			$array['revision_id'] = $this->get_revision()->get_id();
		}

		return $array;
	}
}