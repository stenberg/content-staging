<?php
/**
 * @var string $spinner
 */
$spinner = '';

if ( isset( $status ) && $status < 2 ) {
	$spinner  = '<div id="sme-importing" class="sme-cs-message sme-cs-info">';
	$spinner .= '<p><div class="sme-loader-gif"></div> Importing...</p>';
	$spinner .= '</div>';
}

?>

<div class="wrap">
	<h2>Deploying Batch</h2>

	<div class="sme-deploy-messages">
		<?php foreach ( $messages as $message ) { ?>
			<div class="sme-cs-message sme-cs-<?php echo $message->get_level(); ?>">
				<ul>
					<li><?php echo $message->get_message(); ?></li>
				</ul>
			</div>
		<?php } ?>
	</div>

	<?php echo $spinner; ?>
</div>
