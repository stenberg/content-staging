<?php
namespace Me\Stenberg\Content\Staging;

use Exception;

class Background_Process {

	private $command;
	private $pid;

	public function __construct( $command ) {
		$this->command = $command;
	}

	public function run( $output_file = '/dev/null' ) {
		$this->pid = shell_exec( sprintf(
			'%s > %s 2>&1 & echo $!',
			$this->get_command(),
			$output_file
		) );
	}

	public function is_running() {
		try {
			$result = shell_exec( sprintf( 'ps %d', $this->get_pid() ) );
			if ( count( preg_split( "/\n/", $result ) ) > 2 ) {
				return true;
			}
		} catch( Exception $e ) {}

		return false;
	}

	public function get_pid() {
		return $this->pid;
	}

	public function get_command() {
		return $this->command;
	}
}
