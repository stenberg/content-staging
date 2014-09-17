jQuery( document ).ready(function($) {

	var app = {

		/**
		 * Init method of this application. Contains a simple router.
		 */
		init: function() {

			// Check if global variable 'adminpage' is defined.
			if (typeof adminpage !== 'undefined') {

				/*
				 * Make sure posts in batch cookie is emptied if this is not the edit
				 * batch page.
				 */
				if (adminpage !== 'admin_page_sme-edit-batch') {
					document.cookie = 'wp-sme-bpl=';
				}

				// Simple router.
				switch (adminpage) {
					case 'admin_page_sme-edit-batch':
						this.editBatch();
						break;
					case 'admin_page_sme-send-batch':
						this.deployBatch();
						break;
				}
			}
		},

		/**
		 * User is currently on the Edit Batch page.
		 *
		 * If this is a new batch we would only have data from cookie. If user
		 * goes to next page of posts all posts selected on the previous page will
		 * be stored in cookie and inserted into HTML form.
		 *
		 * If user visit any other (content staging) page, cookie should be
		 * cleared.
		 */
		editBatch: function() {

			var batch;
			var batchId       = $('#sme-batch-id').html();
			var posts         = $('.sme-select-post');
			var postIdsObj    = $('input[name="post_ids"]');
			var postIds       = [];
			var tmpPostIds    = [];
			var cookie;
			var int;
			var i;

			// Get posts from cookie if cookie is not more then 15 min old.
			cookie = document.cookie.replace(/(?:(?:^|.*;\s*)wp-sme-bpl\s*\=\s*([^;]*).*$)|^.*$/, '$1');

			if (cookie === '') {
				/*
				 * Cookie is empty, use post IDs from HTML form as selected posts.
				 */
				tmpPostIds = postIdsObj.val().split(',');
			} else {
				/*
				 * Cookie has been populated. Use post IDs from cookie as selected posts.
				 */

				// Split batch and posts.
				batch = cookie.split(':');

				/*
				 * We are not editing the same batch as cookie is referring to, reset
				 * cookie.
				 */
				if (batch[0] !== batchId) {
					document.cookie = 'wp-sme-bpl=';
				} else {
					// Add posts to array.
					tmpPostIds = batch[1].split(',');
				}
			}

			// Convert to integers and sort out any values that are not a number.
			for (i = 0; i < tmpPostIds.length; i++) {
				int = parseInt(tmpPostIds[i]);
				if ( ! isNaN(int)) {
					postIds[i] = int;
				}
			}

			// Add currently selected post IDs to HTML form.
			postIdsObj.val(postIds.join());

			// Go through all posts and determine which should be selected.
			posts.each(function() {
				if (postIds.indexOf(parseInt($(this).val())) > -1) {
					$(this).prop('checked', true);
				} else {
					$(this).prop('checked', false);
				}
			});

			// User has selected a post.
			posts.click(postIds, function() {
				var postId = parseInt($(this).val());

				if ( $(this).prop('checked') ) {
					postIds.push(postId);
				} else {
					for (i = 0; i < postIds.length; i++) {
						if (postIds[i] === postId) {
							postIds.splice(i, 1);
						}
					}
				}

				// Add post IDs to HTML form.
				postIdsObj.val(postIds.join());

				// Add post IDs to cookie.
				document.cookie = 'wp-sme-bpl=' + batchId + ':' + postIds.join();
			});
		},

		/**
		 * User is currently on the Deploy Batch page.
		 */
		deployBatch: function() {

			var data            = {};
			var batchImporterId = $('#sme-batch-importer-id').html();
			var printed         = 0;

			// Check if a batch importer ID has been found.
			if (batchImporterId) {

				data.action      = 'sme_batch_import_status';
				data.importer_id = batchImporterId;

				this.getBatchImporterStatus(data, printed);
			}
		},

		/**
		 * Get batch import status.
		 *
		 * Since WordPress 2.8 'ajaxurl' is always defined in the admin header
		 * and points to admin-ajax.php.
		 *
		 * @param data
		 * @param printed Number of messages that has been printed.
		 */
		getBatchImporterStatus: function(data, printed) {

			$.post(ajaxurl, data, function(response) {

				// Number of messages in this response.
				var nbrOfMsg = response.messages.length;

				// Only print messages we haven't printed before.
				for (var i = printed; i < nbrOfMsg; i++) {
					$('.wrap').append('<div class="sme-cs-message sme-cs-' + response.messages[i].level + '"><p>' + response.messages[i].message + '</p></div>');
					printed++;
				}

				if (response.status < 2) {
					setTimeout(this.getBatchImporterStatus(data, printed), 3000);
				}
			});
		}
	};

	// Initialize application.
	app.init();

});