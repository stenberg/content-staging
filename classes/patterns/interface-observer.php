<?php
namespace Me\Stenberg\Patterns\Observer;

interface Observer {
	function update( Observable $observable );
}