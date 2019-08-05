<?php
namespace sixbank\payment;
use \sixbank\helper\WC_Sixbank_Helper as WC_Sixbank_Helper;
use \Gateway\API\Acquirers as Acquirers;
/**
 * WC Sixbank Credit Gateway Class.
 *
 * Built the Sixbank Credit methods.
 */
class WC_Sixbank_Credit_Gateway extends WC_Sixbank_Helper {

	/**
	 * Sixbank WooCommerce API.
	 *
	 * @var WC_Sixbank_API
	 */
	public $api = null;

	/**
	 * Gateway actions.
	 */
	public function __construct() {
		$this->id           = 'sixbank_credit';
		$this->icon         = apply_filters( 'WC_Sixbank_credit_icon', '' );
		$this->has_fields   = true;
		$this->method_title = __( 'Sixbank - Credit Card', 'sixbank-woocommerce' );
		$this->supports     = array( 'products', 'refunds' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->merchant_id          = $this->get_option( 'merchant_id' );
		$this->merchant_key         = $this->get_option( 'merchant_key' );		
		$this->soft_descriptor      = $this->get_option( 'soft_descriptor' );
		$this->payment_methods      = $this->get_option( 'payment_methods' );
		$this->antifraud     		= $this->get_option( 'antifraud' );		
		$this->antifraud_option     = $this->get_option( 'antifraud_option' );		
		$this->capture     			= $this->get_option( 'capture' );		
		$this->environment          = $this->get_option( 'environment' );		
		$this->methods              = $this->get_option( 'methods' );		
		$this->smallest_installment = $this->get_option( 'smallest_installment' );
		$this->interest_rate        = $this->get_option( 'interest_rate' );
		$this->installments         = $this->get_option( 'installments' );
		$this->interest             = $this->get_option( 'interest' );		
		$this->design               = $this->get_option( 'design' );
		$this->debug                = $this->get_option( 'debug' );
		$this->min_value  			= $this->get_option( 'min_value' );
		$this->validate_rg_cpf  	= $this->get_option( 'validate_rg_cpf' );
		$this->validate_valid_cpf 	= $this->get_option( 'validate_valid_cpf' );
		$this->validate_valid_cpf = $this->get_option( 'validate_valid_cpf' );
		$this->validate_name_holder = $this->get_option( 'validate_name_holder' );
		$this->validate_card_date = $this->get_option( 'validate_card_date' );
		$this->validate_cvv = $this->get_option( 'validate_cvv' );
		$this->validate_expired_date = $this->get_option( 'validate_expired_date' );
		$this->validate_recurrent_product = $this->get_option( 'validate_recurrent_product' );
		

		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = $this->get_logger();
		}

		// Set the API.
		$this->api = new \sixbank\api\WC_Sixbank_API( $this );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_sixbank_credit_gateway', array( $this, 'check_return' ) );
		add_action( 'woocommerce_' . $this->id . '_return', array( $this, 'return_handler' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );		
		// Filters.
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'order_items_payment_details' ), 10, 2 );
	}

	/*public function check_return(){
		header( 'HTTP/1.1 200 OK' );
		$order_id = isset($_GET['order']) ? $_GET['order'] : null;			
		if (is_null($order_id)) return;						
		$order = wc_get_order( $order_id );
		$order->payment_complete();
		wc_reduce_stock_levels($order_id);
		header('Location: ' . $order->get_checkout_order_received_url());
	}*/

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'sixbank-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Sixbank Credit Card', 'sixbank-woocommerce' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Credit Card', 'sixbank-woocommerce' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'sixbank-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Pay using the secure method of Sixbank', 'sixbank-woocommerce' ),
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Merchant ID from Sixbank.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'xx', 'sixbank-woocommerce' ),
				'class'       => 'onlynumber'
			),
			'merchant_key' => array(
				'title'       => __( 'Merchant Key', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Merchant Key from Sixbank.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'xxxx', 'sixbank-woocommerce' ),
			),
			'environment' => array(
				'title'       => __( 'Environment', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the environment type (test or production).', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'test',
				'options'     => array(
					'test'       => __( 'Test', 'sixbank-woocommerce' ),
					'production' => __( 'Production', 'sixbank-woocommerce' ),
				),
			),
			'soft_descriptor' => array(
				'title'       => __( 'Soft Descriptor', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => '',
				'desc_tip'    => true,				
			),
			'payment_methods' => array(
				'title'       => __( 'Accepted Payment Method', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the payment methods that will be accepted as payment. Press the Ctrl key to select more than one brand.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => array( 'cielo' ),
				'options'     => array(
					'adiq'       => __( 'ADIQ - Webservice', 'sixbank-woocommerce' ),					
					'firstdata'       => __( 'BIN', 'sixbank-woocommerce' ),
					'cielo_loja'       => __( 'CIELO - BUY PAGE LOJA', 'sixbank-woocommerce' ),
					'cielo'       => __( 'CIELO - BUY PAGE CIELO', 'sixbank-woocommerce' ),
					'cielo_api'       => __( 'CIELO - SOLUÇÃO API 3.0', 'sixbank-woocommerce' ),
					'erede'        => __( 'e-Rede Webservice', 'sixbank-woocommerce' ),					
					'getnet'   => __( 'GETNET', 'sixbank-woocommerce' ),
					'granito'       => __( 'Granito Pagamentos', 'sixbank-woocommerce' ),
					'global_payments'       => __( 'GLOBAL PAYMENTS', 'sixbank-woocommerce' ),
					'komerci_webservice'       => __( 'REDE - KOMERCI WEBSERVICE', 'sixbank-woocommerce' ),
					'komerci_integrado'       => __( 'REDE - KOMERCI INTEGRADO', 'sixbank-woocommerce' ),						
					'privatelabel'     => __( 'PrivateLabel', 'sixbank-woocommerce' ),					
					'stone'       => __( 'STONE PAGAMENTOS', 'sixbank-woocommerce' ),
					'worldpay'       => __( 'World Pay', 'sixbank-woocommerce' ),					
					'sixbank'       => __( 'Sixbank', 'sixbank-woocommerce' ),					
				),
			),
			'antifraud' => array(
				'title'       => __( 'Antifraud', 'sixbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable antifraud', 'sixbank-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Enable antifraud', 'sixbank-woocommerce' ),
			),
			'antifraud_option' => array(
				'title'       => __( 'Antifraud Option', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the antifraud option.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'konduroscore',
				'options'     => array(
					'kondutoscore' => __( 'Konduto Score', 'sixbank-woocommerce' ),
					'clearsale' => __( 'Clear Sale', 'sixbank-woocommerce' ),
					'fcontrol' => __( 'Fcontrol', 'sixbank-woocommerce' ),					
				),
			),
			'capture' => array(
				'title'       => __( 'Capture', 'sixbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable automatic capture', 'sixbank-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Enable automatic capture.', 'sixbank-woocommerce' ),
			),			
				
			
			'smallest_installment' => array(
				'title'       => __( 'Smallest Installment', 'sixbank-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Smallest value of each installment, cannot be less than 5.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '5',
				'class'       => 'onlynumber'
			),
			'installments' => array(
				'title'       => __( 'Installment Within', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Maximum number of installments for orders in your store.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => '1',
				'options'     => array(
					'1'  => '1x',
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x',
				),
			),			
			'interest_rate' => array(
				'title'       => __( 'Interest Rate (%)', 'sixbank-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Percentage of interest that will be charged to the customer in the installment where there is interest rate to be charged.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '2',
			),
			'interest' => array(
				'title'       => __( 'Charge Interest Since', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Indicate from which installment should be charged interest.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => '6',
				'options'     => array(
					'1'  => '1x',
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x',
				),
			),		
			'min_value' => array(
				'title'       => __( 'Valor mínimo para exibição (R$)', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Valor mínimo para exibição da opção de pagamento', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '2,00',
				'class'       => 'onlycurrency'
			),				
			'debug' => array(
				'title'       => __( 'Debug Log', 'sixbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'sixbank-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Sixbank events, such as API requests, inside %s', 'sixbank-woocommerce' ), $this->get_log_file_path() ),
			),/*
			'validate_rg_cpf' => array(
				'title'       => __( 'Validação CPF', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite seu RG ou CPF.',
			),
			'validate_valid_cpf' => array(
				'title'       => __( 'Validação CPF digitado', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite um CPF válido.',
			),*/
			'validate_name_holder' => array(
				'title'       => __( 'Validação titular do cartão', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite o nome do titular do cartão.',
			),
			'validate_card_date' => array(
				'title'       => __( 'Validação data de validade', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite a data de validade do cartão.',
			),
			'validate_cvv' => array(
				'title'       => __( 'Validação do CVV', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite o cvv do cartão.',
			),
			'validate_expired_date' => array(
				'title'       => __( 'Validação da data de validade', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'A data de validade do cartão expirou.',
			),
			'validate_recurrent_product' => array(
				'title'       => __( 'Validação carrinho produto recorrente', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Seu carrinho possui produto de outro tipo, é possível apenas um tipo de produto / um produto recorrente',
			),
		);
	}

	public function get_fraud_method(){
		$sd = $this->antifraud_option;
		if ($sd == 'kondutoscore') $method = 'score';
		if ($sd == 'clearsale') $method = 'start';
		if ($sd == 'fcontrol') $method = 'score';
		return $method;

	}
	public function get_fraud_operator(){
		$sd = $this->antifraud_option;
		if ($sd == 'kondutoscore') $operator = 'konduto';
		if ($sd == 'clearsale') $operator = 'clearsale';
		if ($sd == 'fcontrol') $operator = 'fcontrol';
		return $operator;
	}

	public function capture($order, $tid, $amount = NULL){
		$this->api->do_capture($order, $tid, $amount);
	}

	public function report($order, $tid){
		return $this->api->do_report($order, $tid);
	}

	/**
	 * Get Checkout form field.
	 *
	 * @param string $model
	 * @param float  $order_total
	 */
	protected function get_checkout_form( $model = 'webservice', $order_total = 0 ) {
		global $woocommerce;
		$installments_type = ( 'icons' == $model ) ? 'radio' : 'select';
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
		} else {
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		}

		file_put_contents("D:\\xampp7\\htdocs\\minhabolsa\\payment_gateway.log", date('Y-m-d H:i - ') . " - get_checkout_form - ". print_r($order_total, true) . PHP_EOL, FILE_APPEND);

		$_is_sub = false;
		// Gets order total from "pay for order" page.
		if ( $order_id <= 0 ) {
			foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
				$_product = $values['data']; 
				if ($_product->is_type('sixbank_subscription')){            
					$_is_sub = true;         
				}
			}
		}else{
			$order = wc_get_order( $order_id );
			foreach( $order->get_items() as $item_id => $item ){
				//Get the WC_Product object
				$_product = $item->get_product();
				if ($_product->is_type('sixbank_subscription')){
					$_is_sub = true; 
				}
			}
		}
		
		wc_get_template(
			'credit-card/' . $model . '-payment-form.php',
			array(				
				'methods'         => $this->get_available_methods_options(),
				'installments'    => $this->get_installments_html( $order_total, $installments_type ),
				'_is_sub' => $_is_sub
			),
			'woocommerce/sixbank/',
			\sixbank\WC_Sixbank::get_templates_path()
		);
	}	

	public function get_acquirer(){
		$pm = $this->payment_methods;
		if ($pm == 'adiq') $name = Acquirers::ADIQ;
		if ($pm == 'cielo_loja') $name = Acquirers::CIELO_BUY_PAGE_LOJA;
		if ($pm == 'cielo') $name = Acquirers::CIELO_BUY_PAGE_CIELO;
		if ($pm == 'cielo_api') $name = Acquirers::CIELO_V3;
		if ($pm == 'granito') $name = Acquirers::GRANITO;		
		if ($pm == 'global_payments') $name = Acquirers::GLOBAL_PAYMENT;
		if ($pm == 'getnet') $name = Acquirers::GETNET;
		if ($pm == 'erede') $name = Acquirers::REDE_E_REDE;
		if ($pm == 'firstdata') $name = Acquirers::FIRSTDATA;		
		if ($pm == 'komerci_webservice') $name = Acquirers::REDE_KOMERCI_WEBSERVICE;
		if ($pm == 'komerci_integrado') $name = Acquirers::REDE_KOMERCI_INTEGRADO;
		if ($pm == 'privatelabel') $name = Acquirers::VERANCARD;
		if ($pm == 'stone') $name = Acquirers::STONE;
		if ($pm == 'worldpay') $name = Acquirers::WORLDPAY;
		if ($pm == 'sixbank') $name = Acquirers::SIXBANK;
			
		
		
		return $name;
	}

	/**
	 * Checkout scripts.
	 */
	public function checkout_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$suffix = '';
		wp_enqueue_style( 'wc-sixbank-checkout-webservice' );
		wp_enqueue_script( 'wc-sixbank-credit-checkout-webservice', plugins_url( 'assets/js/credit-card/checkout-webservice' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery', 'wc-credit-card-form' ), \sixbank\WC_Sixbank::VERSION, true );
		
	}

	/**
	 * Process webservice payment.
	 *
	 * @param  WC_Order $order
	 *
	 * @return array
	 */
	protected function process_webservice_payment( $order ) {
		$payment_url = '';
		$card_number = isset( $_POST['sixbank_credit_number'] ) ? sanitize_text_field( $_POST['sixbank_credit_number'] ) : '';
		$card_brand  = $this->api->get_card_brand( $card_number );
	
		$valid = $this->validate_card_fields( $_POST, $this->validate_name_holder, $this->validate_card_date, $this->validate_cvv );
		
		if ( $valid ){
			$valid = $this->validate_expiration_date( $_POST,  $this->validate_expired_date );
		}
		
		/*if ($this->antifraud == 'yes' && $valid){
			//Valida CPF e RG
			$valid = $this->validate_slip_fields( $_POST, $this->validate_rg_cpf, $this->validate_valid_cpf );
		}*/

		$cpf = get_post_meta($order->get_id(), '_billing_cpf', true);
		$rg = get_post_meta($order->get_id(), '_billing_rg', true);
		if (isset($cpf) && !empty($cpf) && (!isset( $_POST[ 'billing_cpf'] ) || '' === $_POST[ 'billing_cpf' ]) ){
			$_POST['billing_cpf'] = $cpf;
		}
		if (isset($rg) && !empty($rg) && (!isset( $_POST[ 'billing_rg'] ) || '' === $_POST[ 'billing_rg' ])){
			$_POST['billing_rg'] = $rg;
		}
		/*if ($valid){
			$valid = $this->validate_cpf_fields( $_POST, $this->validate_valid_cpf );
		}*/

		// Test the installments.
		if ( $valid ) {
			$valid = $this->validate_installments( $_POST, $order->get_total() );
		}

		if ( $valid ) {
			$installments = isset( $_POST['sixbank_credit_installments'] ) ? absint( $_POST['sixbank_credit_installments'] ) : 1;
			$card_data    = array(
				'name_on_card'    => $_POST['sixbank_credit_holder_name'],
				'card_number'     => $_POST['sixbank_credit_number'],
				'card_expiration' => $_POST['sixbank_credit_expiry'],
				'card_cvv'        => $_POST['sixbank_credit_cvv'],
				'payment_method'  => $_POST['sixbank_payment_method'],
			);

			//Atualiza RG/CPF da compra
			$order->update_meta_data( '_billing_rg', $_POST['billing_rg']);
			$order->update_meta_data( '_billing_cpf', $_POST['billing_cpf']);
			$order->save();
			
			$response = $this->api->do_transaction( $order, $order->get_id() . '-' . time(), $card_brand, $installments, $card_data, 2 );
					
			// Set the error alert.
			if ( ! empty( $response->message ) ) {
				$this->add_error( (string) $response->message );
				$valid = false;
			}else{

				// Save the tid.
				if ( ! empty( $response->getTransactionID() ) ) {
					update_post_meta( $order->get_id(), '_transaction_id', (string) $response->getTransactionID() );
				}

				// Set the transaction URL.
				if ( ! empty( $response->getRedirectUrl() ) ) {
					$payment_url = (string) $response->getRedirectUrl();
				} else {
					$payment_url = str_replace( '&amp;', '&', urldecode( $this->get_api_return_url( $order ) ) );
				}

				$status = $response->getStatus();
				if ( 8 == $status ) {
					// Complete the payment and reduce stock levels.
					$order->payment_complete();
					$order->update_status( 'processing' );					
				}

				// Save payment data.
				update_post_meta( $order->get_id(), '_WC_Sixbank_card_brand', $card_brand );
				update_post_meta( $order->get_id(), '_WC_Sixbank_installments', $installments );
			}
		}

		if ( $valid && $payment_url ) {
			return array(
				'result'   => 'success',
				'redirect' => $payment_url,
			);
		} else {
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}


	/**
	 * Payment details.
	 *
	 * @param  array    $items
	 * @param  WC_Order $order
	 *
	 * @return array
	 */
	public function order_items_payment_details( $items, $order ) {
		if ( $this->id === $order->payment_method ) {
			$card_brand   = get_post_meta( $order->get_id(), '_WC_Sixbank_card_brand', true );
			$card_brand   = $this->get_payment_method_name( $card_brand );
			$installments = get_post_meta( $order->get_id(), '_WC_Sixbank_installments', true );
			
			$items['payment_method']['value'] .= sprintf( __( '%s in %s.', 'sixbank-woocommerce' ), esc_attr( $card_brand ), $this->get_installment_text( $installments, (float) $order->get_total() ) );
			
		}

		return $items;
	}
}
