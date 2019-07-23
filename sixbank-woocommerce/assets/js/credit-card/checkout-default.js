(function( $ ) {
	'use strict';

	$( function() {
		// Store the installment options.
		$.data( document.body, 'sixbank_credit_installments', $( '#sixbank-credit-payment-form #sixbank-installments' ).html() );

		/**
		 * Set the installment fields.
		 *
		 * @param {string} card
		 */
		function setInstallmentsFields( card ) {
			var installments = $( '#sixbank-credit-payment-form #sixbank-installments' );

			$( '#sixbank-credit-payment-form #sixbank-installments' ).empty();
			$( '#sixbank-credit-payment-form #sixbank-installments' ).prepend( $.data( document.body, 'sixbank_credit_installments' ) );

			if ( 'discover' === card ) {
				$( 'option', installments ).not( '.sixbank-at-sight' ).remove();
			}
		}

		// Set on update the checkout fields.
		$( 'body' ).on( 'ajaxComplete', function() {
			$.data( document.body, 'sixbank_credit_installments', $( '#sixbank-credit-payment-form #sixbank-installments' ).html() );
			setInstallmentsFields( $( 'body #sixbank-credit-payment-form #sixbank-card-brand option' ).first().val() );
		});

		// Set on change the card brand.
		$( 'body' ).on( 'change', '#sixbank-credit-payment-form #sixbank-card-brand', function() {
			setInstallmentsFields( $( ':selected', $( this ) ).val() );
		});
	});

}( jQuery ));
