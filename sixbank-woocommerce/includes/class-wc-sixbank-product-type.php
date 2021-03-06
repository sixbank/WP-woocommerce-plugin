<?php
//add_filter( 'product_type_selector', 'sixbank_add_custom_product_type' );
add_filter( 'init', 'sixbank_create_custom_product_type' );
add_filter( 'woocommerce_product_class', 'sixbank_woocommerce_product_class', 10, 2 );
add_action( 'admin_footer', 'simple_subscription_custom_js' );
add_action( 'admin_footer', 'admin_options' );
add_filter( 'woocommerce_add_to_cart_validation', 'is_product_the_same_type',10,3);
add_action( 'woocommerce_single_product_summary', 'sixbank_subscription_template', 60 );
add_action( 'woocommerce_sixbank_subscription_to_cart', 'sixbank_subscription_add_to_cart', 30 );
//add_action( 'woocommerce_product_options_general_product_data', 'sixbank_product_fields' );
//add_action( 'woocommerce_process_product_meta', 'sixbank_product_fields_save' );
add_filter( 'woocommerce_cart_item_quantity', 'sixbank_product_change_quantity', 10, 3);
add_action('woocommerce_check_cart_items', 'validate_all_cart_contents');
add_filter( 'pre_option_woocommerce_default_gateway' . '__return_false', 99 );
add_action('woocommerce_pay_order_before_submit', 'teste');
function teste(){
    global $woocoomerce;

    $order_total = 0;
    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
        $order_id = absint( get_query_var( 'order-pay' ) );
    } else {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
    }

    $order = wc_get_order($order_id);
    $cpf = get_post_meta($order->get_id(), '_billing_cpf', true);
    $rg = get_post_meta($order->get_id(), '_billing_rg', true);
    echo "<script>
    jQuery(document).ready(function($){
        $('#sixbank_data').prependTo($('#payment'));
    });
    </script>";
    echo '<div id="sixbank_data">
    <p class="form-row rg" id="billing_rg_field" data-priority="">
    <label for="billing_rg" class="">RG&nbsp;<span class="optional">(opcional)</span></label>
    <span class="woocommerce-input-wrapper">
    <input type="number" class="input-text " name="billing_rg" id="billing_rg" placeholder="RG" value="'.$rg.'">
    </span>
    </p>

    <p class="form-row cpf" id="billing_cpf_field" data-priority="">
    <label for="billing_cpf" class="">CPF&nbsp;<span class="optional">(opcional)</span></label>
    <span class="woocommerce-input-wrapper">
    <input type="number" class="input-text " name="billing_cpf" id="billing_cpf" placeholder="CPF" value="'.$cpf.'">
    </span></p>
    
    </div>';
}
function validate_all_cart_contents(){        
    if(WC()->cart->cart_contents_count == 0){
         return true;
    }
    
    $count = 0;
    $othertype = false;
    $prevGroup = false;
    
    foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
        $_product = $values['data']; 
        
        //Verificar se está em algum grupo ou se é recorrente        
        
        $group = wc_get_first_parent($values['product_id']);
        if (!$prevGroup)
            $prevGroup = $group;
        if ($group)
        $sixbank_recurrent = get_post_meta($group, 'sixbank_product_recurrent', true);
        else
        $sixbank_recurrent = get_post_meta($values['product_id'], 'sixbank_product_recurrent', true);

        if ($sixbank_recurrent == 'yes' && $prevGroup == $group){            
        //if ($_product->is_type('sixbank_subscription')){            
            $count++;            
        }else{
            $othertype = true;
        }
    }
        
    $payment_gateway = WC()->payment_gateways->payment_gateways()['sixbank_credit'];
    $message = property_exists( $payment_gateway , 'validate_recurrent_product' ) ? $payment_gateway->validate_recurrent_product : '';
    if($count > 0 && $othertype)  {
        wc_add_notice( $message, 'error' );
        return false;
    }if ($count > 0){
        WC()->session->set('cart_recurrent', true);
        WC()->session->set('group_id', $prevGroup);
    }else{
        WC()->session->set('cart_recurrent', false);
        return true;
    }
}

function wc_get_first_parent($prod_id, $plain_id = true) {
    $group_args = array(
      'post_type' => 'product',
      'meta_query' => array(
        array(
          'key' => '_children',
          'value' => 'i:' . $prod_id . ';',
          'compare' => 'LIKE',
        )
      )
     );
    $parents = get_posts( $group_args );
    $ret_prod = count($parents) > 0 ? array_shift($parents) : false;
    if ($ret_prod && $plain_id) {
      $ret_prod = $ret_prod->ID;
    }
    return $ret_prod;
}
function wpb_hook_javascript() {
    ?>
        <script>
            jQuery(document).ready(function($){
                $("#rigid-account-holder .woocommerce-notices-wrapper").appendTo("#products-wrapper .woocommerce-notices-wrapper");
            });
          
        </script>
    <?php
}
add_action('wp_head', 'wpb_hook_javascript');

function admin_options() {
    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
    $suffix = '';
    wp_enqueue_script( 'wc-sixbank-admin', plugins_url( 'assets/js/admin/admin' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), \sixbank\WC_Sixbank::VERSION, true );
    
}

function sixbank_subscription_add_to_cart() {
    $template_path = WP_PLUGIN_DIR . '/sixbank-woocommerce/templates/';
		// Load the template
    wc_get_template( 'single-product/add-to-cart/sixbank_subscription.php',
        '',
        '',
        trailingslashit( $template_path ) );
}

function sixbank_create_custom_product_type(){
    class WC_Product_Custom extends WC_Product {
        public function __construct( $product ){
            parent::__construct( $product );
        }
        
        public function get_type() {
            return 'sixbank_subscription';
        }
    }
}

function sixbank_add_custom_product_type( $types ){
    $types[ 'sixbank_subscription' ] = 'Sixbank Recorrência';
    return $types;
}
            
// --------------------------
// #3 Load New Product Type Class

function sixbank_woocommerce_product_class( $classname, $product_type ) {
    if ( $product_type == 'sixbank_subscription' ) { 
        $classname = 'WC_Product_Custom';
    }
    return $classname;
}

function simple_subscription_custom_js() {

	if ( 'product' != get_post_type() ) :
		return;
	endif;

	?><script type='text/javascript'>
		jQuery( document ).ready( function() {
            jQuery('.product_data_tabs .general_tab').addClass('show_if_simple show_if_sixbank_subscription').show();
			jQuery('.options_group.pricing').addClass( 'show_if_sixbank_subscription' ).show();
		});
	</script><?php
}


/**
 * 
 * Permitir apenas produto do mesmo tipo (Recorrencia) no carrinho
 * 
 * **/
function is_product_the_same_type($valid, $product_id, $quantity) {
    global $woocommerce;
    
    if($woocommerce->cart->cart_contents_count == 0){
         return true;
    }
    
    $count = 0;
    $othertype = false;
    $prevGroup = false;
    foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
        $_product = $values['data']; 
        
        $group = wc_get_first_parent($values['product_id']);
        if (!$prevGroup)
            $prevGroup = $group;
        if ($group)
        $sixbank_recurrent = get_post_meta($group, 'sixbank_product_recurrent', true);
        else
        $sixbank_recurrent = get_post_meta($values['product_id'], 'sixbank_product_recurrent', true);
    
        if ($sixbank_recurrent == 'yes' && $prevGroup == $group){
        //if ($_product->is_type('sixbank_subscription')){            
            $count++;            
        }else{
            $othertype = true;
        }
    }
    $_is_sub = false;
    $_product = wc_get_product( $product_id );

    //$sixbank_recurrent = get_post_meta($product_id, 'sixbank_product_recurrent', true);
    $productGroup = wc_get_first_parent($product_id);    
    if ($productGroup)
    $sixbank_recurrent = get_post_meta($productGroup, 'sixbank_product_recurrent', true);
    else
    $sixbank_recurrent = get_post_meta($product_id, 'sixbank_product_recurrent', true);

    if ($sixbank_recurrent == 'yes'){
    //if ($_product->is_type('sixbank_subscription')){        
        $_is_sub = true;            
    }
        
    $payment_gateway = WC()->payment_gateways->payment_gateways()['sixbank_credit'];
    $message = property_exists( $payment_gateway , 'validate_recurrent_product' ) ? $payment_gateway->validate_recurrent_product : '';       
    if(($othertype && $_is_sub) || ($count > 0 && $prevGroup != $productGroup))  {
        wc_add_notice( $message, 'error' );
        return false;
    }else{
        return $valid;
    }
}

function sixbank_subscription_template () {
	global $product;
	if ( 'sixbank_subscription' == $product->get_type() ) {
		$template_path = WP_PLUGIN_DIR . '/sixbank-woocommerce/templates/';
		// Load the template
		wc_get_template( 'single-product/add-to-cart/sixbank_subscription.php',
			'',
			'',
			trailingslashit( $template_path ) );
	}
}

function sixbank_product_fields() {
    echo "<div class='options_group show_if_sixbank_subscription'>";

        $select_field = array(
            'id' => 'sixbank_subscription_period',
            'label' => __( 'Every', 'sixbank-woocommerce' ),
            'data_type' => 'number',
            'options' => array(
                'day' => __('Day', 'sixbank-woocommerce'),
                'week' => __('Week', 'sixbank-woocommerce'),
                'month' => __('Month', 'sixbank-woocommerce'),
                'year' => __('Year', 'sixbank-woocommerce')
            ),
            'desc_tip' => __('Period that charges will be made', 'sixbank-woocommerce')
        );
        woocommerce_wp_select( $select_field );

        $select_field = array(
        'id' => 'sixbank_subscription_days',
        'label' => __( 'Expire after (in days)', 'sixbank-woocommerce' ),
        'data_type' => 'number',
        'placeholder' => '30',
        'value' => '30',
        'custom_attributes' => array( 'min' => '1' ),
        'desc_tip' => __('Duration of recurrence in days', 'sixbank-woocommerce'),
        );
        woocommerce_wp_text_input( $select_field );

        $select_field = array(
            'id' => 'sixbank_subscription_frequency',
            'label' => __( 'Times', 'sixbank-woocommerce' ),
            'data_type' => 'number',            
            'desc_tip' => __('Amount of charges', 'sixbank-woocommerce')
        );
        woocommerce_wp_text_input( $select_field );

        
    echo "</div>";
}

function sixbank_product_fields_save( $post_id ){    
    // Number Field
    $sixbank_subscription_days = $_POST['sixbank_subscription_days'];
    update_post_meta( $post_id, 'sixbank_subscription_days', esc_attr( $sixbank_subscription_days ) );
    // Textarea
    $sixbank_subscription_frequency = $_POST['sixbank_subscription_frequency'];
    update_post_meta( $post_id, 'sixbank_subscription_frequency', esc_html( $sixbank_subscription_frequency ) );
    // Select
    $sixbank_subscription_period = $_POST['sixbank_subscription_period'];
    update_post_meta( $post_id, 'sixbank_subscription_period', esc_attr( $sixbank_subscription_period ) );
}

function sixbank_product_change_quantity( $product_quantity, $cart_item_key, $cart_item ) {
    $product_id = $cart_item['product_id'];
    $product = wc_get_product($product_id);
    // whatever logic you want to determine whether or not to alter the input
    $sixbank_recurrent = get_post_meta($product_id, 'sixbank_product_recurrent', true);    
    if (WC()->session->get('cart_recurrent')){
    //if ( $product->is_type('sixbank_subscription') ) {
        return '<span>1</span>';
    }

    return $product_quantity;
}

?>