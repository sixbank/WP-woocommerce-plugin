<?php
/**
 * Debit Card - Webservice checkout form.
 *
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<fieldset id="sixbank-debit-payment-form" class="sixbank-payment-form">
	<p class="form-row form-row-first">
		<label for="sixbank-card-number"><?php _e( 'Card Number', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-number" name="sixbank_debit_number" class="input-text wc-credit-card-form-card-number" type="tel" maxlength="22" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="sixbank-card-holder-name"><?php _e( 'Name Printed on the Card', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-holder-name" name="sixbank_debit_holder_name" class="input-text" type="text" autocomplete="off" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<div class="clear"></div>
	<p class="form-row form-row-first">
		<label for="sixbank-card-expiry"><?php _e( 'Expiry (MM/YYYY)', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-expiry" name="sixbank_debit_expiry" class="input-text wc-credit-card-form-card-expiry" type="tel" autocomplete="off" placeholder="<?php _e( 'MM / YYYY', 'sixbank-woocommerce' ); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="sixbank-card-cvv"><?php _e( 'Security Code', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-cvv" name="sixbank_debit_cvv" class="input-text wc-credit-card-form-card-cvv" type="tel" maxlength="4" autocomplete="off" placeholder="<?php _e( 'CVV', 'sixbank-woocommerce' ); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>	
	<?php if ( 0 < $discount ) : ?>		
		<p class="form-row form-row-wide discount">
			<?php printf( __( 'Payment by debit have discount of %s. Order Total: %s.', 'sixbank-woocommerce' ), $discount . '%', sanitize_text_field( wc_price( $discount_total ) ) ); ?>
		</p>
		<p style="display: none" class="discount-text"><?php printf( '%s', sanitize_text_field( wc_price( $discount_total ) ) ); ?><p>
	<?php endif; ?>
	<div class="clear"></div>
</fieldset>
