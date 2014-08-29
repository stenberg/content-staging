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

				console.log(response);
				var status;
				var nbrOfMsg = 0;

				for (var level in response) {
					for (var i = 0; i < response[level].length; i++) {

						var result = response[level][i].substring(0, 15);

						if (result == 'Import status: ') {
							status = response[level][i].replace('Import status: ', '');
						} else {
							nbrOfMsg++;

							console.log('Message number: ' + nbrOfMsg);
							console.log('Printed messages: ' + nbrOfPrintedMsg);
							console.log(response[level][i]);

							if (nbrOfMsg > nbrOfPrintedMsg) {
								$('.wrap').append('<div class="sme-cs-message sme-cs-' + level + '"><p>' + response[level][i] + '</p></div>');
								nbrOfPrintedMsg++;
							}
						}
					}
				}

				if (status < 2) {
					getBatchImporterStatus();
				}
			});
		}, 3000);
	}

});