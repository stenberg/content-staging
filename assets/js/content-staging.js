jQuery( document ).ready(function($) {

	var batchImporterId = $('#sme-batch-importer-id').html();
	var nbrOfPrintedMsg = 0;

	// Check if a batch importer ID has been found.
	if (batchImporterId) {
		var data = {
			'action': 'sme_batch_import_status',
			'importer_id': batchImporterId
		};
		getBatchImporterStatus();
	}

	function getBatchImporterStatus() {
		setTimeout(function () {

			/*
			 * Since WordPress 2.8 ajaxurl is always defined in the admin header and
			 * points to admin-ajax.php.
			 */
			$.post(ajaxurl, data, function(response) {

				// Number of messages in this response.
				var nbrOfMsg = 0;

				for (var level in response.messages) {
					for (var i = 0; i < response.messages[level].length; i++) {

						nbrOfMsg++;

						// Only print messages we haven't printed before.
						if (nbrOfMsg > nbrOfPrintedMsg) {
							$('.wrap').append('<div class="sme-cs-message sme-cs-' + level + '"><p>' + response.messages[level][i] + '</p></div>');
							nbrOfPrintedMsg++;
						}
					}
				}

				if (response.status < 2) {
					getBatchImporterStatus();
				}
			});
		}, 3000);
	}

});