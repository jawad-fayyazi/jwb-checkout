/* global jwb_params */
( function ( $ ) {
	'use strict';

	// Individual / Group / Gift purchase-type toggle.
	$( document ).on( 'click', '.jwb-toggle-btn', function () {
		var $btn      = $( this );
		var direction = $btn.data( 'direction' );
		var $toggle   = $btn.closest( '.jwb-purchase-type-toggle' );

		// Disabled buttons (the active one) cannot be clicked.
		if ( $btn.prop( 'disabled' ) ) { return; }

		// Disable all buttons while the swap is in progress.
		$toggle.find( '.jwb-toggle-btn' ).prop( 'disabled', true );

		$.post( jwb_params.ajax_url, {
			action:    'jwb_swap_cart',
			nonce:     jwb_params.nonce,
			direction: direction,
		}, function ( response ) {
			if ( response.success ) {
				// Full page reload so PHP re-renders the toggle, recipient fields,
				// and cart totals in the correct state.
				window.location.reload();
			} else {
				alert( ( response.data && response.data.message ) || 'Something went wrong. Please try again.' );
				// Re-enable all buttons except the originally active one.
				$toggle.find( '.jwb-toggle-btn' ).prop( 'disabled', false );
				$toggle.find( '.jwb-toggle-btn.is-active' ).prop( 'disabled', true );
			}
		} ).fail( function () {
			alert( 'Network error. Please try again.' );
			$toggle.find( '.jwb-toggle-btn' ).prop( 'disabled', false );
			$toggle.find( '.jwb-toggle-btn.is-active' ).prop( 'disabled', true );
		} );
	} );

	// Copy coupon code on thank-you page.
	$( document ).on( 'click', '.jwb-copy-btn', function () {
		var $btn  = $( this );
		var code  = $btn.data( 'code' );

		if ( ! code ) { return; }

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( code ).then( function () {
				jwb_copied_feedback( $btn );
			} ).catch( function () {
				jwb_fallback_copy( code, $btn );
			} );
		} else {
			jwb_fallback_copy( code, $btn );
		}
	} );

	function jwb_copied_feedback( $btn ) {
		var original = $btn.text();
		$btn.text( 'Copied!' );
		setTimeout( function () {
			$btn.text( original );
		}, 2000 );
	}

	function jwb_fallback_copy( text, $btn ) {
		var $temp = $( '<textarea>' );
		$( 'body' ).append( $temp );
		$temp.val( text ).select();
		try {
			document.execCommand( 'copy' );
			jwb_copied_feedback( $btn );
		} catch ( e ) {
			// Silent fail.
		}
		$temp.remove();
	}

} )( jQuery );
