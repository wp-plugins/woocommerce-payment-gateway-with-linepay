function doRefund(msg, url, orderId, transactionId) {
	var a = confirm(msg);

	if (a) {
		var params = "transactionId="+transactionId+"&orderId="+orderId;

		jQuery.ajax({
			type: 		'POST',
			url: 		url,
			data: 		params,
			success: 	function( code ) {
					try {
						var result = jQuery.parseJSON( code );

						if (result.result == 'success') {
							alert(result.message);
							location.reload();
						} else if (result.result == 'failure') {
							alert(result.message);
							location.reload();
						} else {
							throw "Invalid response";
						}
					} catch(err) {
						jQuery(document).prepend(code);
					}
				},
			dataType: 	"html"
		});
	}
}