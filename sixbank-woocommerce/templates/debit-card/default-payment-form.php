<?php
/**
 * Debit Card - Default checkout form.
 *
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<fieldset id="sixbank-debit-payment-form" class="sixbank-payment-form">
	<p class="form-row form-row-wide">
		<?php if ( 1 < count( $methods ) ) : ?>
			<label for="sixbank-card-brand"><?php _e( 'Debit Card', 'sixbank-woocommerce' ); ?> <span class="required">*</span></label>
			<select id="sixbank-card-brand" name="sixbank_debit_card" style="font-size: 1.5em; padding: 4px; width: 100%;">
				<?php foreach ( $methods as $method_key => $method_name ) : ?>
					<option value="<?php echo esc_attr( $method_key ); ?>"><?php echo esc_attr( $method_name ); ?></option>
				<?php endforeach ?>
			</select>
		<?php else : ?>
			<span><?php printf( __( 'Pay with %s.', 'sixbank-woocommerce' ), current( $methods ) ); ?></span>
			<input type="hidden" name="sixbank_debit_card" value="<?php echo esc_attr( key( $methods ) ); ?>" />
		<?php endif; ?>
	</p>
	<?php if ( 0 < $discount ) : ?>
		<p class="form-row form-row-wide">
			<?php printf( __( 'Payment by debit have discount of %s. Order Total: %s.', 'sixbank-woocommerce' ), $discount . '%', sanitize_text_field( woocommerce_price( $discount_total ) ) ); ?>
		</p>
	<?php endif; ?>
	<div class="clear"></div>
</fieldset>
