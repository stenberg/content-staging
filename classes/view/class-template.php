<?php
namespace Me\Stenberg\Content\Staging\View;

class Template {

	private $template_dir;

	public function __construct( $template_dir ) {
		$this->template_dir = $template_dir;
	}

	/**
	 * Render template.
	 *
	 * @param string $template Name of the template file without the .php extension.
	 * @param array $data Array where each key corresponds to what variable name you want you
	 *                    want to use in your template to accesss the data.
	 */
	public function render( $template, $data = array() ) {
		extract( $data );
		require_once( $this->template_dir . $template . '.php' );
	}
}