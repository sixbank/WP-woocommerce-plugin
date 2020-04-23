<?php
/**
 * Credit Card - Webservice checkout form.
 *
 * @version 4.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<fieldset id="sixbank-credit-payment-form" class="sixbank-payment-form">	
	<div class="clear"></div>	
	<p class="form-row form-row-first">
		<label for="sixbank-card-number"><?php _e( 'Card Number', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-number" name="sixbank_credit_number" class="input-text wc-credit-card-form-card-number" type="tel" maxlength="22" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="sixbank-card-holder-name"><?php _e( 'Name Printed on the Card', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-holder-name" maxlength="20" name="sixbank_credit_holder_name" class="input-text" type="text" autocomplete="off" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<div class="clear"></div>
	<p class="form-row form-row-first">
		<label for="sixbank-card-expiry"><?php _e( 'Expiry (MM/YYYY)', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-expiry" name="sixbank_credit_expiry" class="input-text wc-credit-card-form-card-expiry" type="tel" autocomplete="off" placeholder="<?php _e( 'MM / YYYY', 'sixbank-woocommerce' ); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="sixbank-card-cvv"><?php _e( 'Security Code', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
		<input id="sixbank-card-cvv" name="sixbank_credit_cvv" class="input-text wc-credit-card-form-card-cvv" type="tel" maxlength="4" autocomplete="off" placeholder="<?php _e( 'CVV', 'sixbank-woocommerce' ); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	
	<?php if ( ! empty( $installments ) && !$_is_sub ) : ?>
		<p class="form-row form-row-wide">
			<label for="sixbank-installments"><?php _e( 'Installments', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
			<?php echo $installments; ?>
		</p>
	<?php endif; 
	if ($_is_sub): ?>
		
	<?php endif; ?>
	<div class="clear"></div>
</fieldset>
