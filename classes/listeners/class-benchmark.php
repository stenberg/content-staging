<?php
namespace Me\Stenberg\Content\Staging\Listeners;

class Benchmark {

	/**
	 * @var array
	 */
	private $hooks;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->hooks = array();

		/*
		 * Hooks to benchmark. Each value in the array consists of a hook to
		 * start timing from and a hook to end timing on.
		 */
		$hooks = apply_filters( 'sme_benchmark', array() );

		/*
		 * Attach a timer start and end function to the provided start and end
		 * hooks.
		 */
		foreach ( $hooks as $hook ) {
			if ( ! isset( $hook['start_hook'] ) || ! isset( $hook['end_hook'] ) ) {
				continue;
			}
			add_action( $hook['start_hook'], array( $this, 'time_start' ), -999 );
			add_action( $hook['end_hook'], array( $this, 'time_end' ), 999 );
			$this->hooks[] = $hook;
		}
	}

	public function time_start() {
		for ( $i = 0; $i < count( $this->hooks ); $i++ ) {
			if ( current_filter() == $this->hooks[$i]['start_hook'] ) {
				$this->hooks[$i]['start'] = microtime( true );
				break;
			}
		}
	}

	public function time_end() {
		for ( $i = 0; $i < count( $this->hooks ); $i++ ) {
			if ( current_filter() == $this->hooks[$i]['end_hook'] ) {
				$this->hooks[$i]['end'] = microtime( true );
				error_log(
					sprintf(
						'[%s / %s]: %f s',
						$this->hooks[$i]['start_hook'],
						$this->hooks[$i]['end_hook'],
						( $this->hooks[$i]['end'] - $this->hooks[$i]['start'] )
					)
				);
				break;
			}
		}
	}
}