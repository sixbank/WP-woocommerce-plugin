<?php
namespace sixbank\payment;
use \sixbank\helper\WC_Sixbank_Helper as WC_Sixbank_Helper;

use \Gateway\API\Acquirers as Acquirers;
/**
 * WC Sixbank Slip Gateway Class.
 *
 * Built the Sixbank Slip methods.
 */
class WC_Sixbank_Slip_Gateway extends WC_Sixbank_Helper {

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
		$this->id           = 'sixbank_slip';
		$this->icon         = apply_filters( 'WC_Sixbank_slip_icon', '' );
		$this->has_fields   = true;
		$this->method_title = __( 'Sixbank - Slip', 'sixbank-woocommerce' );
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
		$this->environment      = $this->get_option( 'environment' );						
		$this->slip_discount   	= $this->get_option( 'slip_discount' );
		$this->design           = $this->get_option( 'design' );
		$this->debug            = $this->get_option( 'debug' );
		$this->payment_methods  = $this->get_option( 'payment_methods' );
		$this->instructions  	= $this->get_option( 'instructions' );
		$this->min_value  		= $this->get_option( 'min_value' );
		$this->slip_expire  	= $this->get_option( 'slip_expire' );
		$this->slip_text  		= $this->get_option( 'slip_text' );
		$this->validate_cpf  	= $this->get_option( 'validate_cpf' );
		$this->validate_rg  	= $this->get_option( 'validate_rg' );
		$this->validate_valid_cpf = $this->get_option( 'validate_valid_cpf' );

		
		
		
		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = $this->get_logger();
		}

		// Set the API.
		$this->api = new \sixbank\api\WC_Sixbank_API( $this );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_sixbank_slip_gateway', array( $this, 'check_return' ) );
		add_action( 'woocommerce_' . $this->id . '_return', array( $this, 'return_handler' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ), 999 );
		
		add_action( 'woocommerce_view_order', array($this, 'wc_sixbank_pending_payment_instructions' ), 5);

		// Filters.
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'order_items_payment_details' ), 10, 2 );
	}

	function wc_sixbank_pending_payment_instructions( $order_id ) {
		$order = new \WC_Order( $order_id );	

		if ( 'pending' === $order->status && 'sixbank_slip' == $order->payment_method ) {
			$html = '<div class="woocommerce-info">';
			$html .= sprintf( '<a class="button" href="%s" target="_blank">%s</a>', get_post_meta( $order->get_id(), '_slip_url', true ), __( 'Billet print', 'boletosimples-woocommerce' ) );
			$message = sprintf( __( '%sAttention!%s Not registered the billet payment for this order yet.', 'boletosimples-woocommerce' ), '<strong>', '</strong>' ) . '<br />';
			$message .= __( 'Please click the following button and pay the billet in your Internet Banking.', 'boletosimples-woocommerce' ) . '<br />';			
			$message .= __( 'Ignore this message if the payment has already been made​​.', 'boletosimples-woocommerce' ) . '<br />';
			$html .= apply_filters( 'woocommerce_boletosimples_pending_payment_instructions', $message, $order );
			$html .= '</div>';
			echo $html;
		}
	}

	public function check_return(){
		header( 'HTTP/1.1 200 OK' );
		$order_id = isset($_GET['order']) ? $_GET['order'] : null;			
		if (is_null($order_id)) return;						
		$order = wc_get_order( $order_id );
		
		wc_reduce_stock_levels($order_id);
		header('Location: ' . $order->get_checkout_order_received_url());
	}
	
	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'sixbank-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Sixbank Slip', 'sixbank-woocommerce' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Slip', 'sixbank-woocommerce' ),
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
			'payment_methods' => array(
				'title'       => __( 'Accepted Payment Method', 'sixbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the payment methods that will be accepted as payment. Press the Ctrl key to select more than one brand.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => array( 'itau' ),
				'options'     => array(
					'bradesco' 	 => __( 'Bradesco Shopfácil', 'sixbank-woocommerce' ),									
					'cielo' 	 => __( 'Cielo', 'sixbank-woocommerce' ),					
					'itau'       => __( 'Itaú Shopline', 'sixbank-woocommerce' ),
					'sixbank'       => __( 'Sixbank', 'sixbank-woocommerce' ),					
				),
			),							
			'slip_discount' => array(
				'title'       => __( 'Slip Discount (%)', 'sixbank-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Percentage discount for payments made ​​by slip.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '0',
			),
			'slip_expire' => array(
				'title'       => __( 'Vencimento em (dias)', 'sixbank-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Quantidade de dias para vencimento do boleto', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '1',
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'sixbank-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Essas instruções serão exibidas na descrição do boleto.', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Não receber após vencimento', 'sixbank-woocommerce' ),
			),
			'min_value' => array(
				'title'       => __( 'Valor mínimo para exibição (R$)', 'sixbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Valor mínimo para exibição da opção de pagamento', 'sixbank-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '3,00',
				'class'       => 'onlycurrency'
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'sixbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'sixbank-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Sixbank events, such as API requests, inside %s', 'sixbank-woocommerce' ), $this->get_log_file_path() ),
			),
			'slip_text' => array(
				'title'       => __( 'Texto boleto', 'sixbank-woocommerce' ),
				'type'        => 'textarea',
				'description' => 'Texto que é exibido após escolher boleto como opção de pagamento.',
				'desc_tip'    => true,
				'default'     => 'Atenção! Seu pedido foi realizado com sucesso porém, seu pagamento ainda precisa ser confirmado.
				Por favor, clique no botão para visualizar e pagar o boleto.',
			)/*,
			'validate_cpf' => array(
				'title'       => __( 'Validação CPF', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite seu CPF.',
			),
			'validate_rg' => array(
				'title'       => __( 'Validação RG', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite seu RG.',
			),
			'validate_valid_cpf' => array(
				'title'       => __( 'Validação CPF digitado', 'sixbank-woocommerce' ),
				'type'        => 'text',				
				'desc_tip'    => true,
				'default'     => 'Por favor, digite um CPF válido.',
			)*/
		);
	}

	/**
	 * Get Checkout form field.
	 *
	 * @param string $model
	 * @param float  $order_total
	 */
	protected function get_checkout_form( $model = 'webservice', $order_total = 0 ) {
		wc_get_template(
			'slip/' . $model . '-payment-form.php',
			array(				
				'discount'       => $this->slip_discount,
				'discount_total' => $this->get_slip_discount( $order_total ),
			),
			'woocommerce/sixbank/',
			\sixbank\WC_Sixbank::get_templates_path()
		);
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
		
		$valid = true;

		$cpf = get_post_meta($order->get_id(), '_billing_cpf', true);
		$rg = get_post_meta($order->get_id(), '_billing_rg', true);
		if (isset($cpf) && !empty($cpf) && (!isset( $_POST[ 'billing_cpf'] ) || '' === $_POST[ 'billing_cpf' ]) ){
			$_POST['billing_cpf'] = $cpf;
		}
		if (isset($rg) && !empty($rg) && (!isset( $_POST[ 'billing_rg'] ) || '' === $_POST[ 'billing_rg' ])){
			$_POST['billing_rg'] = $rg;
		}
		$valid = true;//$this->validate_rg_cpf_fields( $_POST, $this->validate_rg, $this->validate_cpf, $this->validate_valid_cpf );

		if ( $valid ) {			

			//Atualiza RG/CPF da compra
			$order->update_meta_data( '_billing_rg', $_POST['billing_rg']);
			$order->update_meta_data( '_billing_cpf', $_POST['billing_cpf']);
			$order->save();
			
			$response = $this->api->do_transaction( $order, $order->get_id() . '-' . time(), '', 0, array(), 3 );

			if (!method_exists($response, 'getResponse')){
				$this->add_error(  'Não foi possível processar seu pagamento. Tente novamente.' );
				$valid = false;
			}			

			if ($valid){
				// Set the error alert.
				if ( ! empty( $response->getResponse()->errorCode ) ) {
					$this->add_error( (string) $response->getResponse()->message );
					$valid = false;
				}
				

				$status = $response->getResponse()['status'];
								
				if ( 'BOLETO' === $status || 0 === $status) {
					// Complete the payment and reduce stock levels.
					$order->update_status( 'on-hold' );
				}else if ($status === 2 || $status === 'UNAUTHORIZED' || 4 === $status){
					$order->update_status( 'failed' );
				}
				

				// Set the transaction URL.
				if ( ! empty( $response->{'url-autenticacao'} ) ) {
					$payment_url = (string) $response->{'url-autenticacao'};
				} else {
					$payment_url = str_replace( '&amp;', '&', urldecode( $this->get_api_return_url( $order ) ) );
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
		$card_brand  = isset( $_POST['sixbank_slip_card'] ) ? sanitize_text_field( $_POST['sixbank_slip_card'] ) : '';

		// Validate credit card brand.
		$valid = $this->validate_credit_brand( $card_brand );

		if ( $valid ) {
			$card_brand = ( 'visaelectron' === $card_brand ) ? 'visa' : 'mastercard';
			$response   = $this->api->do_transaction( $order, $order->get_id() . '-' . time(), $card_brand, 0, array(), 3 );

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
			if ( ! empty( $response->{'url-autenticacao'} ) ) {
				$payment_url = (string) $response->{'url-autenticacao'};
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

	public function get_acquirer(){
		$pm = $this->payment_methods;
		$name = Acquirers::ITAU_SHOPLINE;;
		if ($pm == 'bradesco') $name = Acquirers::BRADESCO_SHOPFACIL_BOLETO;		
		if ($pm == 'cielo') $name = Acquirers::CIELO_V3;		
		if ($pm == 'sixbank') $name = Acquirers::SIXBANK;		
		
		return $name;
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
			
			$items['payment_method']['value'] .= esc_attr( $card_brand );

			if ( 0 < $this->slip_discount ) {
				$discount_total = $this->get_slip_discount( (float) $order->get_total() );

				$items['payment_method']['value'] .= ' ';
				$items['payment_method']['value'] .= sprintf( __( 'with discount of %s. Order Total: %s.', 'sixbank-woocommerce' ), $this->slip_discount . '%', sanitize_text_field( wc_price( $discount_total ) ) );
			}
			
		}

		return $items;
	}
}
