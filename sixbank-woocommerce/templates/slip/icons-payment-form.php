<?php
/**
 * Slip - Icons checkout form.
 *
 * @version 4.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$first_method = current( $methods );

?>

<fieldset id="sixbank-slip-payment-form" class="sixbank-payment-form">
	<ul id="sixbank-card-brand">
		<?php foreach ( $methods as $method_key => $method_name ): ?>
			<li><label title="<?php echo esc_attr( $method_name ); ?>"><i id="sixbank-icon-<?php echo esc_attr( $method_key ); ?>"></i><input type="radio" name="sixbank_slip_card" value="<?php echo esc_attr( $method_key ); ?>" <?php echo ( $first_method == $method_name ) ? 'checked="checked"' : ''; ?>/><span><?php echo esc_attr( $method_name ); ?></span></label></li>
		<?php endforeach ?>
	</ul>

	<div class="clear"></div>

	<?php if ( ! empty( $installments ) ) : ?>
		<p id="sixbank-select-name"><?php _e( 'Pay with', 'sixbank-woocommerce' ); ?> <strong><?php echo esc_attr( $first_method ); ?></strong></p>

		<div id="sixbank-installments">
			<p class="form-row">
				<?php echo $installments; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="clear"></div>
</fieldset>
