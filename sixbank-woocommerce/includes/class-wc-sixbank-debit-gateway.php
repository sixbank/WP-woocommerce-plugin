<?php
/**
 * WC Sixbank Debit Gateway Class.
 *
 * Built the Sixbank Debit methods.
 */
class WC_Sixbank_Debit_Gateway extends WC_Sixbank_Helper {

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
		$this->id           = 'sixbank_debit';
		$this->icon         = apply_filters( 'WC_Sixbank_debit_icon', '' );
		$this->has_fields   = true;
		$this->method_title = __( 'Sixbank - Debit Card', 'sixbank-woocommerce' );
		$this->supports     = array( 'products', 'refunds' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->merchant_id      = $this->get_option( 'merchant_id' );
		$this->merchant_key     = $this->get_option( 'merchant_key' );	
		$this->soft_descriptor  = $this->get_option( 'soft_descriptor' );	
		$this->environment      = $this->get_option( 'environment' );						
		$this->antifraud     		= $this->get_option( 'antifraud' );		
		$this->antifraud_option     = $this->get_option( 'antifraud_option' );
		$this->payment_methods     		= $this->get_option( 'payment_methods' );	
		$this->debit_discount   = $this->get_option( 'debit_discount' );
		$this->design           = $this->get_option( 'design' );
		$this->debug            = $this->get_option( 'debug' );
		$this->min_value  		= $this->get_option( 'min_value' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = $this->get_logger();
		}

		// Set the API.
		$this->api = new WC_Sixbank_API( $this );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_sixbank_debit_gateway', array( $this, 'check_return' ) );
		add_action( 'woocommerce_' . $this->id . '_return', array( $this, 'return_handler' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ), 999 );

		// Filters.
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'order_items_payment_details' ), 10, 2 );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'sixbank-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Sixbank Debit Card', 'sixbank-woocommerce' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Debit Card', 'sixbank-woocommerce' ),
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
				'default'     => '',
			),	
			'environment' => array(
				'title'       => __( 'Environment', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select the environment type (test or production).', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
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
					'firstdata'       => __( 'BIN', 'sixbank-woocommerce' ),
					'cielo_loja'       => __( 'CIELO - BUY PAGE LOJA', 'sixbank-woocommerce' ),					
					'cielo_api'       => __( 'CIELO - SOLUÇÃO API 3.0', 'sixbank-woocommerce' ),
					'erede'        => __( 'e-Rede Webservice', 'sixbank-woocommerce' ),					
					'getnet'   => __( 'GETNET V1', 'sixbank-woocommerce' ),					
					'global_payments'       => __( 'GLOBAL PAYMENTS', 'sixbank-woocommerce' ),															
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
			'debit_discount' => array(
				'title'       => __( 'Debit Discount (%)', 'sixbank-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Percentage discount for payments made ​​by debit card.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '0',
			),
			'min_value' => array(
				'title'       => __( 'Valor mínimo para exibição (R$)', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Valor mínimo para exibição da opção de pagamento', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '2,00',
				'class'       => 'onlycurrency'
			),
			'design_options' => array(
				'title'       => __( 'Design', 'sixbank-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'sixbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'sixbank-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Sixbank events, such as API requests, inside %s', 'sixbank-woocommerce' ), $this->get_log_file_path() ),
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

	/**
	 * Get Checkout form field.
	 *
	 * @param string $model
	 * @param float  $order_total
	 */
	protected function get_checkout_form( $model = 'webservice', $order_total = 0 ) {
		wc_get_template(
			'debit-card/' . $model . '-payment-form.php',
			array(				
				'discount'       => $this->debit_discount,
				'discount_total' => $this->get_debit_discount( $order_total ),
			),
			'woocommerce/sixbank/',
			WC_Sixbank::get_templates_path()
		);
	}

	public function get_acquirer(){
		$pm = $this->payment_methods;		
		if ($pm == 'cielo_loja') $name = Acquirers::CIELO_BUY_PAGE_LOJA;		
		if ($pm == 'cielo_api') $name = Acquirers::CIELO_V3;		
		if ($pm == 'global_payments') $name = Acquirers::GLOBAL_PAYMENT;
		if ($pm == 'getnet') $name = Acquirers::GETNET_V1;
		if ($pm == 'erede') $name = Acquirers::REDE_E_REDE;
		if ($pm == 'firstdata') $name = Acquirers::FIRSTDATA;				
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

		if ( 'icons' == $this->design ) {
			wp_enqueue_style( 'wc-sixbank-checkout-icons' );
		}
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
		$card_number = isset( $_POST['sixbank_debit_number'] ) ? sanitize_text_field( $_POST['sixbank_debit_number'] ) : '';
		$card_brand  = $this->api->get_debit_card_brand( $card_number );
		
		$valid = true; //$this->validate_credit_brand( $_card_brand );

		// Test the card fields.
		if ( $valid ) {
			$valid = $this->validate_card_fields( $_POST );
		}

		if ( $valid ) {
			$valid = $this->validate_expiration_date( $_POST );
		}

		$cpf = get_post_meta($order->get_id(), '_billing_cpf', true);
		$rg = get_post_meta($order->get_id(), '_billing_rg', true);
		if (isset($cpf) && !empty($cpf) && (!isset( $_POST[ 'billing_cpf'] ) || '' === $_POST[ 'billing_cpf' ]) ){
			$_POST['billing_cpf'] = $cpf;
		}
		if (isset($rg) && !empty($rg) && (!isset( $_POST[ 'billing_rg'] ) || '' === $_POST[ 'billing_rg' ])){
			$_POST['billing_rg'] = $rg;
		}
		if ($this->antifraud == 'yes' && $valid){
			//Valida CPF e RG
			$valid = $this->validate_slip_fields( $_POST );
		}

		if ($valid){
			$valid = $this->validate_cpf_fields( $_POST );
		}
		
		if ( $valid ) {
			$card_brand = ( 'maestro' === $card_brand ) ? 'mastercard' : $card_brand;
			$card_data  = array(
				'name_on_card'    => $_POST['sixbank_debit_holder_name'],
				'card_number'     => $_POST['sixbank_debit_number'],
				'card_expiration' => $_POST['sixbank_debit_expiry'],
				'card_cvv'        => $_POST['sixbank_debit_cvv'],
			);

			//Atualiza RG/CPF da compra
			$order->update_meta_data( '_billing_rg', $_POST['billing_rg']);
			$order->update_meta_data( '_billing_cpf', $_POST['billing_cpf']);
			$order->save();
			
			
			$response = $this->api->do_transaction( $order, $order->get_id() . '-' . time(), $card_brand, 0, $card_data, 1 );
			
			// Set the error alert.
			if ( ! empty( $response->message ) ) {
				$this->add_error( (string) $response->message );
				$valid = false;
			}else{

				// Save the tid.
				if ( ! empty( $response->tid ) ) {
					update_post_meta( $order->get_id(), '_transaction_id', (string) $response->tid );
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
	 * Process buy page sixbank payment.
	 *
	 * @param  WC_Order $order
	 *
	 * @return array
	 */
	protected function process_buypage_sixbank_payment( $order ) {
		$payment_url = '';
		$card_brand  = isset( $_POST['sixbank_debit_card'] ) ? sanitize_text_field( $_POST['sixbank_debit_card'] ) : '';

		// Validate credit card brand.
		$valid = $this->validate_credit_brand( $card_brand );

		if ($this->antifraud == 'yes' && $valid){
			//Valida CPF e RG
			$valid = $this->validate_slip_fields( $card_brand );
		}

		if ( $valid ) {
			$card_brand = ( 'visaelectron' === $card_brand ) ? 'visa' : 'mastercard';
			$response   = $this->api->do_transaction( $order, $order->get_id() . '-' . time(), $card_brand, 0, array(), 1 );

			// Set the error alert.
			if ( ! empty( $response->mensagem ) ) {
				$this->add_error( (string) $response->mensagem );
				$valid = false;
			}

			// Save the tid.
			if ( ! empty( $response->tid ) ) {
				update_post_meta( $order->get_id(), '_transaction_id', (string) $response->tid );
			}

			// Set the transaction URL.
			if ( ! empty( $response['processor']['urlAuthentication'] ) ) {
				$payment_url = (string) $response['processor']['urlAuthentication'];
				update_post_meta($order->get_id(), '_payment_url', $payment_url);
			}

			update_post_meta( $order->get_id(), '_WC_Sixbank_card_brand', $card_brand );
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

			$items['payment_method']['value'] .= '<br />';
			$items['payment_method']['value'] .= '<small>';
			$items['payment_method']['value'] .= esc_attr( $card_brand );

			if ( 0 < $this->debit_discount ) {
				$discount_total = $this->get_debit_discount( (float) $order->get_total() );

				$items['payment_method']['value'] .= ' ';
				$items['payment_method']['value'] .= sprintf( __( 'with discount of %s. Order Total: %s.', 'sixbank-woocommerce' ), $this->debit_discount . '%', sanitize_text_field( woocommerce_price( $discount_total ) ) );
			}

			$items['payment_method']['value'] .= '</small>';
		}

		return $items;
	}
}
