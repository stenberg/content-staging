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
	 * @var int Post ID on staging environment.
	 */
	private $stage_id;

	/**
	 * @var int The ID assigned to the post on production environment.
	 */
	private $prod_id;

	/**
	 * @var int If the post already exist on production, then store the
	 * current revision ID of the post in this property. Will not be set if
	 * the post is new to production.
	 */
	private $revision_id;

	/**
	 * @var string Post status on staging environment.
	 */
	private $stage_status;

	/**
	 * @var string GUID of parent post.
	 */
	private $parent_guid;

	/**
	 * @param int $stage_id
	 */
	public function __construct( $stage_id ) {
		parent::__construct();
		$this->stage_id = $stage_id;
	}

	/**
	 * @param int $id
	 */
	public function set_stage_id( $id ) {
		$this->stage_id = $id;
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
		$this->prod_id = $id;
	}

	/**
	 * @return int
	 */
	public function get_prod_id() {
		return $this->prod_id;
	}

	/**
	 * @param int $id
	 */
	public function set_revision_id( $id ) {
		$this->revision_id = $id;
	}

	/**
	 * @return int
	 */
	public function get_revision_id() {
		return $this->revision_id;
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
	 * @param string $guid
	 */
	public function set_parent_guid( $guid ) {
		$this->parent_guid = $guid;
	}

	/**
	 * @return string
	 */
	public function get_parent_guid() {
		return $this->parent_guid;
	}

	/**
	 * Get array representation of this object.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'stage_id'     => $this->get_stage_id(),
			'prod_id'      => $this->get_prod_id(),
			'revision_id'  => $this->get_revision_id(),
			'stage_status' => $this->get_stage_status(),
			'parent_guid'  => $this->get_parent_guid(),
		);
	}
}