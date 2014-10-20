jQuery( document ).ready(function($) {

	/**
	 * Since WordPress 2.8 'ajaxurl' is always defined in the admin header
	 * and points to admin-ajax.php.
	 */
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
			var self       = this;
			var batchId    = $('#sme-batch-id').html();
			var titleObj   = $('input[name="batch_title"]');
			var posts      = $('.sme-select-post');
			var postIdsObj = $('input[name="post_ids"]');
			var postIds    = [];
			var selectAll  = $('[id^=cb-select-all-]');
			var cookie;
			var batch;

			// Get value from cookie.
			cookie = document.cookie.replace(/(?:(?:^|.*;\s*)wp-sme-bpl\s*\=\s*([^;]*).*$)|^.*$/, '$1');

			if (cookie !== '') {
				// Split batch and posts.
				batch = cookie.split(':');

				/*
				 * We are not editing the same batch as cookie is referring to, reset
				 * cookie.
				 */
				if (batch[0] !== batchId) {
					document.cookie = 'wp-sme-bpl=::';
				} else {
					// Add posts to array.
					postIds = batch[1].split(',');

					// Set batch title.
					if (batch[2] !== 'undefined') {
						titleObj.val(batch[2]);
					}
				}
			}

			// No selected post IDs found, try to grab them from HTML.
			if (postIds.length <= 0) {
				postIds = postIdsObj.val().split(',');
			}

			// Convert all post IDs to integers.
			postIds = this.arrayValuesToIntegers(postIds);

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

			// User has changed batch title.
			titleObj.change(function() {
				self.updateBatchTitle(batchId, postIds, titleObj.val());
			});

			// User has selected/unselected a post.
			posts.click(function() {
				var postObj = $(this);

				// Add post ID to array of post IDs.
				self.selectPost(postIds, parseInt(postObj.val()), postObj.prop('checked'));

				// Update selected posts.
				self.updateSelectedPosts(batchId, postIds, postIdsObj, titleObj.val());
			});

			// User has selected/unselected all posts.
			selectAll.click(function() {
				var isChecked = $(this).prop('checked');

				posts.each(function() {
					self.selectPost(postIds, parseInt($(this).val()), isChecked);
				});

				// Update selected posts.
				self.updateSelectedPosts(batchId, postIds, postIdsObj, titleObj.val());
			});
		},

		/**
		 * Select a post in Edit Batch view.
		 *
		 * @param {Array} postIds
		 * @param {int} postId
		 * @param {bool} checked
		 */
		selectPost: function(postIds, postId, checked) {
			var i;

			// Remove post ID from array of post IDs.
			for (i = 0; i < postIds.length; i++) {
				if (postIds[i] === postId) {
					postIds.splice(i, 1);
				}
			}

			// Add post ID to array of post IDs.
			if (checked) {
				postIds.push(parseInt(postId));
			}
		},

		/**
		 * Update cookie and HTML form with currently selected posts.
		 *
		 * @param {int} batchId
		 * @param {Array} postIds
		 * @param {Object} postIdsObj
		 * @param {string} batchTitle
		 */
		updateSelectedPosts: function(batchId, postIds, postIdsObj, batchTitle) {
			var str = postIds.join();

			// Add post IDs to HTML form.
			postIdsObj.val(str);

			// Add post IDs to cookie.
			document.cookie = 'wp-sme-bpl=' + batchId + ':' + str + ':' + batchTitle;
		},

		updateBatchTitle: function(batchId, postIds, batchTitle) {
			document.cookie = 'wp-sme-bpl=' + batchId + ':' + postIds.join() + ':' + batchTitle;
		},

		/**
		 * User is currently on the Deploy Batch page.
		 */
		deployBatch: function() {

			var data = {
				action: 'sme_import_request',
				job_id: $('#sme-batch-import-job-id').html(),
				importer: $('#sme-batch-importer-type').html()
			};

			var printed = $('.sme-deploy-messages .sme-cs-message').length;

			// Check if a batch importer ID has been found.
			if (data.job_id && data.importer) {
				this.deployStatus(data, printed);
			}
		},

		/**
		 * Get batch import status.
		 *
		 * @param data
		 * @param printed Number of messages that has been printed.
		 */
		deployStatus: function(data, printed) {

			var self = this;

			$.post(ajaxurl, data, function(response) {

				// Number of messages in this response.
				var nbrOfMsg = response.messages.length;
				var reloadLoader = false;

				// Only print messages we haven't printed before.
				for (var i = printed; i < nbrOfMsg; i++) {
					$('.sme-deploy-messages').append('<div class="sme-cs-message sme-cs-' + response.messages[i].level + '"><p>' + response.messages[i].message + '</p></div>');
					printed++;
				}

				if (response.status > 1) {
					$('#sme-importing').remove();
				}

				// If import is not completed, select import method.
				if (response.status < 2) {
					switch (data.importer) {
						case 'ajax':
							self.ajaxImport(data, printed);
							break;
						case 'background':
							self.backgroundImport(data, printed);
							break;
					}
				}
			});
		},

		ajaxImport: function(data, printed) {
			this.deployStatus(data, printed);
		},

		backgroundImport: function(data, printed) {
			var self = this;
			setTimeout(function() {
				self.deployStatus(data, printed);
			}, 3000);
		},

		/**
		 * Convert array values to integers and sort out any values that are not
		 * a number.
		 *
		 * @param {Array} array
		 * @return {Array}
		 */
		arrayValuesToIntegers: function(array) {
			var i;
			var int;
			var newArray = [];

			for (i = 0; i < array.length; i++) {
				int = parseInt(array[i]);
				if ( ! isNaN(int)) {
					newArray[i] = int;
				}
			}

			return newArray;
		}
	};

	// Initialize application.
	app.init();

});