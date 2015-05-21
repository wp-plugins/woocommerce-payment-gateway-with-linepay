jQuery( '.order-actions > .cancel' ).click(function( $ ) {
	var ask_msg = confirm( jQuery( '#ask-refund-msg' ).val() );

	if ( ! ask_msg ) {
		return false;
	}
});