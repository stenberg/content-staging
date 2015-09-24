<h2><?php _e( 'Deleted Posts', 'sme-content-staging' ); ?></h2>
<p><?php _e( 'Select deleted posts that you want to remove from production.', 'sme-content-staging' ); ?></p>

<table class="wp-list-table widefat extensions" cellspacing="0">
	<thead>
		<tr>
			<th scope="col" class="manage-column column-cb check-column" >
				<input type="checkbox">
			</th>
			<th scope="col" id="deleted_post_titles" class="manage-column column-description" >
				<?php _e( 'Post Title' ); ?>
			</th>
		</tr>
	</thead>

	<tbody>
	<?php foreach ( $deleted_posts as $post ) : ?>
		<tr>
			<th scope="row" class="check-column">
				<input type="checkbox" name="delete_posts[]" <?php echo $post['checked']; ?> value="<?php echo $post['id']; ?>">
			</th>
			<td>
				<?php echo $post['title']; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<div class="tablenav bottom">
	<br class="clear">
</div>