<?php
namespace Me\Stenberg\Content\Staging\Interfaces;

interface Observer {
	public function update( Observable $observable );
}
