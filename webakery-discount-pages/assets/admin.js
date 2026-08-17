( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var field = e.target.closest( '.wdp-link-copy' );
		if ( ! field ) {
			return;
		}
		field.select();
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( field.value ).catch( function () {} );
		} else {
			try {
				document.execCommand( 'copy' );
			} catch ( err ) {
				/* no-op */
			}
		}
	} );
} )();
