<?php
namespace Me\Stenberg\Content\Staging\Models;

class Message extends Model {

	private $message;

	private $level;

	private $type;

	private $related_to;

	public function __construct( $message, $level, $type ) {

	}
}