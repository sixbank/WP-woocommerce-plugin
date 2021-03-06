(function( $ ) {
	'use strict';

	$( function() {
		// Store the installment options.
		$.data( document.body, 'sixbank_credit_installments', $( '#sixbank-credit-payment-form #sixbank-installments' ).html() );
		
		$( document.body ).on('keyup', '#sixbank-card-cvv', function () { 			
			this.value = this.value.replace(/[^0-9\.]/g,'');
		});

		// Add jQuery.Payment support for Elo and Aura.
		if ( $.payment.cards ) {
			var cards = [];

			$.each( $.payment.cards, function( index, val ) {
				cards.push( val.type );
			});
			
			
			if ( typeof $.payment.cards[0].pattern === 'undefined' ) {
				if ( -1 === $.inArray( 'aura', cards ) ) {
					$.payment.cards.unshift({
						type: 'aura',
						patterns: [5078],
						format: /(\d{1,6})(\d{1,2})?(\d{1,11})?/,
						length: [19],
						cvvLength: [3],
						luhn: true
					});
				}
			} else {
				if ( -1 === $.inArray( 'visa', cards ) ) {
					$.payment.cards.push({
						type: 'visa',
						pattern: /^(636[2-3])/,
						format: /(\d{1,4})/g,
						length: [16],
						cvvLength: [3],
						luhn: true
					});
				}

				if ( -1 === $.inArray( 'aura', cards ) ) {
					$.payment.cards.unshift({
						type: 'aura',
						pattern: /^5078/,
						format: /(\d{1,6})(\d{1,2})?(\d{1,11})?/,
						length: [19],
						cvvLength: [3],
						luhn: true
					});
				}
			}
		}

		/**
		 * Set the installment fields.
		 *
		 * @param {String} card
		 */
		function setInstallmentsFields( card ) {
			var installments = $( '#sixbank-credit-payment-form #sixbank-installments' );

			//$( '#sixbank-credit-payment-form #sixbank-installments' ).empty();
			//$( '#sixbank-credit-payment-form #sixbank-installments' ).prepend( $.data( document.body, 'sixbank_credit_installments' ) );

			if ( 'discover' === card ) {
				$( 'option', installments ).not( '.sixbank-at-sight' ).remove();
			}
		}

		// Set on update the checkout fields.
		$( document.body ).on( 'ajaxComplete', function() {
			$.data( document.body, 'sixbank_credit_installments', $( '#sixbank-credit-payment-form #sixbank-installments' ).html() );
			setInstallmentsFields( $( 'body #sixbank-credit-payment-form #sixbank-card-brand option' ).first().val() );
		});

		// Set on change the card brand.
		$( document.body ).on( 'change', '#sixbank-credit-payment-form #sixbank-card-number', function() {
			setInstallmentsFields( $.payment.cardType( $( this ).val() ) );
		});

		// Empty all card fields.
		$( document.body ).on( 'checkout_error', function() {
			$( 'body .sixbank-payment-form input' ).val( '' );
		});
	});

}( jQuery ));
