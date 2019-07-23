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
				$( 'label', installments ).not( '.sixbank-at-sight' ).remove();
			}

			$( 'input:eq(0)', installments ).attr( 'checked', 'checked' );
		}

		// Set on update the checkout fields.
		$( 'body' ).on( 'ajaxComplete', function() {
			$.data( document.body, 'sixbank_credit_installments', $( '#sixbank-credit-payment-form #sixbank-installments' ).html() );
			setInstallmentsFields( $( 'body #sixbank-credit-payment-form #sixbank-card-brand input' ).first().val() );
		});

		// Set on change the card brand.
		$( 'body' ).on( 'click', '#sixbank-credit-payment-form #sixbank-card-brand input', function() {
			$( '#sixbank-credit-payment-form #sixbank-select-name strong' ).html( '<strong>' + $( this ).parent( 'label' ).attr( 'title' ) + '</strong>' );
			setInstallmentsFields( $( this ).val() );
		});
	});

}( jQuery ));
