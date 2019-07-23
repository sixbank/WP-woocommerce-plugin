<?php
/**
 * Plugin Name: Sixbank WooCommerce
 * Plugin URI:  https://www.sixbank.net/
 * Description: Solution to receive payments on WooCommerce.
 * Author:      Evolutap
 * Author URI:  https://www.sixbank.net/
 * Version:     1.0.0
 * License:     GPLv2 or later
 * Text Domain: sixbank-woocommerce
 * Domain Path: /languages
 *
 *
 * You should have received a copy of the GNU General Public License
 * along with Sixbank WooCommerce - Solução Webservice. If not, see
 * <https://www.gnu.org/licenses/gpl-2.0.txt>.
 *
 * @package WC_Sixbank
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $sixbank_db_version;
$sixbank_db_version = '1.0';

if ( ! class_exists( 'WC_Sixbank' ) ) :

	/**
	 * WooCommerce WC_Sixbank main class.
	 */
	class WC_Sixbank {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.0.0';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin public actions.
		 */
		private function __construct() {
			// Load plugin text domain.
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Checks with WooCommerce and WooCommerce is installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				$this->upgrade();
				$this->includes();

				// Add the gateway.
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );

				// Admin actions.
				if ( is_admin() ) {
					add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				}
				
				add_action( 'template_redirect', array($this, 'set_custom_data_wc_session' ));
				add_filter('woocommerce_billing_fields', array($this, 'custom_woocommerce_billing_fields'));
				add_action('woocommerce_order_item_add_action_buttons', array($this, 'action_woocommerce_order_item_add_action_buttons'), 10, 1);
				add_action('save_post', array($this, 'capture_save_action'), 10, 3);
				add_action( 'rest_api_init', function () {
					register_rest_route( 'sixbank/v1', '/sixbank_order_callback', array(
					  'methods' => 'GET',
					  'callback' => array($this, 'sixbank_order_callback'),
					) );
				} );
				add_action( 'rest_api_init', function () {
					register_rest_route( 'sixbank/v1', '/sixbank_order_return', array(
					  'methods' => 'GET',
					  'callback' => array($this, 'sixbank_order_return'),
					) );
					register_rest_route( 'sixbank/v1', '/sixbank_order_return', array(
						'methods' => 'POST',
						'callback' => array($this, 'sixbank_order_return'),
					) );
				} );
				add_filter( 'user_has_cap', array($this, 'order_pay_without_login'), 9999, 3 );					
				add_filter( 'woocommerce_available_payment_gateways', array( $this, 'sixbank_unset_gateway_subscription' ) );
				add_action( 'plugins_loaded', array($this, 'sixbank_update_db_check' ) );				
				add_action( 'init', array($this, 'register_authorized_order_status' ) );
				add_filter( 'wc_order_statuses', array($this,'add_authorized_to_order_statuses' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}
		
		function order_pay_without_login( $allcaps, $caps, $args ) {			
			if ( isset( $caps[0], $_GET['key'] ) ) {
			   if ( $caps[0] == 'pay_for_order' ) {
				  $order_id = isset( $args[2] ) ? $args[2] : null;
				  $order = wc_get_order( $order_id );
				  if ( $order ) {
					 $allcaps['pay_for_order'] = true;
				  }
			   }
			}
			return $allcaps;
		}
		 
		function register_authorized_order_status() {
			register_post_status( 'wc-authorized', array(
				'label'                     => 'Authorized',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Authorized <span class="count">(%s)</span>', 'Awaiting shipment <span class="count">(%s)</span>' )
			) );
		}

		function add_authorized_to_order_statuses( $order_statuses ) {
			$new_order_statuses = array();
			// add new order status after processing
			foreach ( $order_statuses as $key => $status ) {
				$new_order_statuses[ $key ] = $status;
				if ( 'wc-processing' === $key ) {
					$new_order_statuses['wc-authorized'] = __('Authorized', 'sixbank-woocommerce');
				}
			}
			return $new_order_statuses;
		}

		function sixbank_update_db_check() {
			global $sixbank_db_version;
			if ( get_site_option( 'sixbank_db_version' ) != $sixbank_db_version ) {
				$this->sixbank_install();
			}
		}

		function sixbank_install(){
			global $wpdb;
			global $sixbank_db_version;

			$table_name = $wpdb->prefix . 'sixbank_subscription';
			
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				transaction_id VARCHAR(200) NULL,
				date date DEFAULT '0000-00-00' NOT NULL,
				tid tinytext NULL,
				ticket int(10) NOT NULL,
				amount int(10) NOT NULL,
				status int(2) NOT NULL,
				processed datetime NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			add_option( 'sixbank_db_version', $sixbank_db_version );
		}

		function sixbank_order_callback($data){
			global $wpdb;
			file_put_contents("/home/homolog/webapps/homolog-gateway/webhook.log", date('Y-m-d H:i - ') . print_r($data, true), FILE_APPEND);
			$tid = $data['tid'];
			$status = $data['status'];
			$recurrences_id = $data['recurrences_id'];
			//É recorrencia
			if (isset($recurrences_id)){
				//Recuperar order pelo tid
				$sql = "SELECT transaction_id 
				FROM {$wpdb->sixbank_subscription}				
				WHERE ticket = '$recurrences_id' 				
				LIMIT 1";

				$wpdb->query(
					$wpdb->prepare(
						"UPDATE $wpdb->sixbank_subscription 
						SET status='$status' WHERE ticket=$recurrences_id"
					)
				);
				$sql = $wpdb->prepare( $sql, $tid );
				$tid = $wpdb->get_var( $sql );
				
			}
		
			//Recuperar order pelo tid
			$sql = "SELECT post_id 
			FROM {$wpdb->postmeta} pm 
			JOIN {$wpdb->posts} p 
			ON p.ID = pm.post_id 				
				AND post_type = 'shop_order'
			WHERE meta_key = '_sixbank_tid' 
			AND meta_value = '%s'			  
			ORDER BY RAND() 
			LIMIT 1";

			$sql = $wpdb->prepare( $sql, $tid );
			
			// use get_var() to return the post_id
			$order_id = $wpdb->get_var( $sql );
			$order = wc_get_order( $order_id );
			//Atualizar de acordo com status
			switch($status){
				case 0: //Criado
					$order->add_order_note( "Criada" );
					break;
				case 1: //Autenticada
					$order->add_order_note( "Autenticada" );
					break;
				case 2: //Não-autenticada
					$order->add_order_note( "Não-autenticada" );
					break;
				case 3: //Autorizada pela operadora
					$order->add_order_note( "Autorizada pela operadora" );
					$order->update_status( 'authorized' );
					break;
				case 4: //Não-autorizada pela operadora
					$order->add_order_note( "Não-autorizada pela operadora" );
					$order->update_status( 'failed' );
					break;
				case 5: //Em cancelamento
					$order->add_order_note( "Em cancelamento" );
					break;
				case 6: //Cancelado
					$order->add_order_note( "Cancelado" );
					$order->update_status( 'failed' );
					break;
				case 7: //Em captura
					$order->add_order_note( "Em captura" );
					break;
				case 8: //Capturada / Finalizada
					$order->add_order_note( "Capturada / Finalizada" );
					$order->payment_complete();
					break;
				case 9: //Não-capturada
					$order->add_order_note( "Não-capturada" );
					break;
				case 10: //Pagamento Recorrente - Agendada
					$order->add_order_note( "Pagamento Recorrente - Agendada" );
					break;
				case 11: //Boleto Gerado
					$order->add_order_note( "Boleto gerado" );
					break;
			}

			$order->save();
			return array("data" => $order->get_id());
		}

		function sixbank_order_return($data){
			global $wpdb;
			$tid = $data['TransactionID'];
			
			//Recuperar order pelo tid
			$sql = "SELECT post_id 
			FROM {$wpdb->postmeta} pm 
			JOIN {$wpdb->posts} p 
			ON p.ID = pm.post_id 				
				AND post_type = 'shop_order'
			WHERE meta_key = '_sixbank_tid' 
			AND meta_value = '%s'			  
			ORDER BY RAND() 
			LIMIT 1";

			$sql = $wpdb->prepare( $sql, $tid );
			
			// use get_var() to return the post_id
			$order_id = $wpdb->get_var( $sql );
			$order = wc_get_order( $order_id );

			$gateway = new WC_Sixbank_Credit_Gateway();
			$response = $gateway->report($order, $tid);
			$status = $response->getResponse()['status'];
						
			if (!$order){
				echo json_encode(array('data' => 'Order not found'));
				header('HTTP/1.0 204 Not Found', true, 204);
				die();
			}
			//Atualizar de acordo com status
			switch($status){
				case 0: //Criado
					$order->add_order_note( "Criada" );
					break;
				case 1: //Autenticada
					$order->add_order_note( "Autenticada" );
					break;
				case 2: //Não-autenticada
					$order->add_order_note( "Não-autenticada" );
					break;
				case 3: //Autorizada pela operadora
					$order->add_order_note( "Autorizada pela operadora" );
					$order->update_status( 'authorized' );
					break;
				case 4: //Não-autorizada pela operadora
					$order->add_order_note( "Não-autorizada pela operadora" );
					$order->update_status( 'failed' );
					break;
				case 5: //Em cancelamento
					$order->add_order_note( "Em cancelamento" );
					break;
				case 6: //Cancelado
					$order->add_order_note( "Cancelado" );
					$order->update_status( 'cancelled' );
					break;
				case 7: //Em captura
					$order->add_order_note( "Em captura" );
					break;
				case 8: //Capturada / Finalizada
					$order->add_order_note( "Capturada / Finalizada" );
					$order->payment_complete();
					$order->update_status( 'processing' );
					break;
				case 9: //Não-capturada
					$order->add_order_note( "Não-capturada" );
					break;
				case 10: //Pagamento Recorrente - Agendada
					$order->add_order_note( "Pagamento Recorrente - Agendada" );
					break;
				case 11: //Boleto Gerado
					$order->add_order_note( "Boleto gerado" );
					break;
			}

			$order->save();
			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				echo json_encode(array("data" => "OK"));
			}else{
				header('Location: ' . urldecode($gateway->get_api_return_url($order)) );							
			}			
			die();
		}

		// add new button for woocommerce
		
		// define the woocommerce_order_item_add_action_buttons callback
		function action_woocommerce_order_item_add_action_buttons( $order )
		{

			echo '<input type="hidden" id="order_total" value="'.$order->get_total().'"/>';
			if ($order->get_status() == 'authorized'){
				echo '<script type="text/javascript">
				
				</script>';
				echo '<button id="capture_button" name="capture" type="button" class="button generate-items" value="Capture">' . __( 'Capturar', 'sixbank' ) . '</button>';				
				echo '<input type="hidden" id="capture" name="capture" />';
				
				echo '<span style="float: left;">Total captura: </span>';				
				echo '<input style="float: left;" type="text" id="amount_capture" name="amount_capture" />';
				
			}
		}

		
		function capture_save_action($post_id, $post, $update){
			$slug = 'shop_order';
			if(is_admin()){
				// If this isn't a 'woocommercer order' post, don't update it.
				if ( $slug != $post->post_type ) {
					
					return;
				}
				
				if(isset($_POST['capture']) && $_POST['capture']){
					// do your stuff here after you hit submit
					$amount = isset ( $_POST['amount_capture'] ) ? $_POST['amount_capture'] * 100 : NULL;
					$order = wc_get_order($post_id);
					$tid = get_post_meta($order->get_id(), '_sixbank_tid', true);					
					if (isset($tid) && $tid != NULL){
						$gateway = new WC_Sixbank_Credit_Gateway();
						$gateway->capture($order, $tid, $amount);
					}					
				}
			}
		}

		function set_custom_data_wc_session () {
			if ( isset( $_POST['billing_rg'] ) || isset( $_POST['billing_cpf'] ) ) {
				$billing_rg   = isset( $_POST['billing_rg'] )  ? esc_attr( $_POST['billing_rg'] )   : '';
				$billing_cpf = isset( $_POST['billing_cpf'] ) ? esc_attr( $_POST['billing_cpf'] ) : '';
		
				// Set the session data
				WC()->session->set( 'custom_data', array( 'billing_rg' => $billing_rg, 'billing_cpf' => $billing_cpf ) );
			}
		}

		function custom_woocommerce_billing_fields($fields)
		{

			$data = WC()->session->get('custom_data');

			$fields['billing_rg'] = array(
				'label' => __('RG', 'woocommerce'), // Add custom field label
				'placeholder' => _x('RG', 'placeholder', 'woocommerce'), // Add custom field placeholder
				'required' => false, // if field is required or not
				'clear' => false, // add clear or not
				'type'  => 'number',
				'type' => 'number', // add field type
				'class' => array('rg'),    // add class name
				'clear'     => false
			);

			$fields['billing_cpf'] = array(
				'label' => __('CPF', 'woocommerce'), // Add custom field label
				'placeholder' => _x('CPF', 'placeholder', 'woocommerce'), // Add custom field placeholder
				'required' => false, // if field is required or not
				'clear' => false, // add clear or not
				'type' => 'number', // add field type
				'class' => array('cpf')    // add class name
			);
					
			if( isset($data['billing_rg']) && ! empty($data['billing_rg']) )
			$fields['billing_rg']['default'] = $data['billing_rg'];
				
			if( isset($data['billing_cpf']) && ! empty($data['billing_cpf']) )
			$fields['billing_cpf']['default'] = $data['billing_cpf'];

			return $fields;
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Get templates path.
		 *
		 * @return string
		 */
		public static function get_templates_path() {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'sixbank-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Includes.
		 */
		private function includes() {
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-product-type.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-xml.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-helper.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-api.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-debit-gateway.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-credit-gateway.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-slip-gateway.php';			
			include_once dirname( __FILE__ ) . '/includes/class-wc-sixbank-transfer-gateway.php';			
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param   array $methods WooCommerce payment methods.
		 *
		 * @return  array          Payment methods with Sixbank.
		 */
		public function add_gateway( $methods ) {
			array_push( $methods, 'WC_Sixbank_Debit_Gateway', 'WC_Sixbank_Credit_Gateway', 'WC_Sixbank_Slip_Gateway', 'WC_Sixbank_Transfer_Gateway');

			return $methods;
		}

		/**
		 * Upgrade plugin options.
		 */
		private function upgrade() {
			if ( is_admin() ) {
				$version = get_option( 'WC_Sixbank_version', '0' );

				if ( version_compare( $version, WC_Sixbank::VERSION, '<' ) ) {

					// Upgrade from 3.x.
					if ( $options = get_option( 'woocommerce_sixbank_settings' ) ) {
						// Credit.
						$credit_options = array(
						'enabled'              => $options['enabled'],
						'title'                => __( 'Credit Card', 'sixbank-woocommerce' ),
						'description'          => $options['description'],
						'merchant_id'          => __( 'Merchant ID', 'sixbank-woocommerce' ),
						'merchant_key'         => __( 'Merchant Key', 'sixbank-woocommerce' ),
						'payment_methods'      => $options['payment_methods'],
						'antifraud'			   => $options['antifraud'],						
						'environment'          => $options['environment'],						
						'methods'              => $options['methods'],						
						'smallest_installment' => $options['smallest_installment'],
						'interest_rate'        => $options['interest_rate'],
						'installments'         => $options['installments'],
						'interest'             => $options['interest'],											
						'design'               => $options['design'],
						'debug'                => $options['debug'],
						);

						// Debit.
						$debit_methods = array();
						if ( 'mastercard' == $options['debit_methods'] ) {
							$debit_methods = array( 'maestro' );
						} else if ( 'all' == $options['debit_methods'] ) {
							$debit_methods = array( 'visaelectron', 'maestro' );
						} else {
							$debit_methods = array( 'visaelectron' );
						}

						$debit_options  = array(
						'enabled'        => ( 'none' == $options['debit_methods'] ) ? 'no' : $options['enabled'],
						'title'          => __( 'Debit Card', 'sixbank-woocommerce' ),
						'description'    => $options['description'],
						'merchant_id'    => __( 'Merchant ID', 'sixbank-woocommerce' ),
						'merchant_key'   => __( 'Merchant Key', 'sixbank-woocommerce' ),						
						'environment'    => $options['environment'],						
						'methods'        => $debit_methods,						
						'debit_discount' => $options['debit_discount'],
						'design_options' => $options['design_options'],
						'design'         => $options['design'],
						'debug'          => $options['debug'],
						);

						// Save the new options.
						update_option( 'woocommerce_sixbank_credit_settings', $credit_options );
						update_option( 'woocommerce_sixbank_debit_settings', $debit_options );

						// Delete old options.
						delete_option( 'woocommerce_sixbank_settings' );
					}

					update_option( 'WC_Sixbank_version', WC_Sixbank::VERSION );
				}
			}
		}

		/**
		 * Register scripts.
		 */
		public function register_scripts() {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			// Styles.
			wp_register_style( 'wc-sixbank-checkout-icons', plugins_url( 'assets/css/checkout-icons' . $suffix . '.css', __FILE__ ), array(), WC_Sixbank::VERSION );
			wp_register_style( 'wc-sixbank-checkout-webservice', plugins_url( 'assets/css/checkout-webservice' . $suffix . '.css', __FILE__ ), array(), WC_Sixbank::VERSION );

			wp_enqueue_script( 'wc-sixbank-checkout-ws', plugins_url( 'assets/js/checkout-ws.js', __FILE__ ), array( 'jquery' ), WC_Sixbank::VERSION, true );			
			
		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			include_once dirname( __FILE__ ) . '/includes/views/notices/html-notice-woocommerce-missing.php';
		}

		/**
		 * Action links.
		 *
		 * @param  array $links
		 *
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array();

			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
				$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=sixbank_credit' ) ) . '">' . __( 'Credit Card Settings', 'sixbank-woocommerce' ) . '</a>';
				$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=sixbank_debit' ) ) . '">' . __( 'Debit Card Settings', 'sixbank-woocommerce' ) . '</a>';
			} else {
				$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=WC_Sixbank_credit_gateway' ) ) . '">' . __( 'Credit Card Settings', 'sixbank-woocommerce' ) . '</a>';
				$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=WC_Sixbank_debit_gateway' ) ) . '">' . __( 'Debit Card Settings', 'sixbank-woocommerce' ) . '</a>';
			}

			return array_merge( $plugin_links, $links );
		}

		
		function sixbank_unset_gateway_subscription( $available_gateways ) {
			$order_total = 0;
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
				$order_id = absint( get_query_var( 'order-pay' ) );
			} else {
				$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
			}		
			
			$unset = false;		
			if ($order_id <= 0){					
				foreach ( WC()->cart->get_cart_contents() as $key => $values ) {
					$_product = $values['data'];
					if ($_product->is_type('sixbank_subscription')){
						$unset = true;
					}
				}
				$order_total = WC()->cart->total;;
			}else{
				$order = wc_get_order( $order_id );
				foreach( $order->get_items() as $item_id => $item ){
					//Get the WC_Product object
					$_product = $item->get_product();
					if ($_product->is_type('sixbank_subscription')){
						$unset = true;
					}
				}	
				$order_total = $order->get_total();			
			}
			//Verifica valor mínimo para uso
			foreach ($available_gateways as $gateway_id => $gateway){
				$min_value = property_exists( $gateway , 'min_value' ) ? $gateway->min_value : 3;
				$discount = property_exists( $gateway , 'slip_discount' ) ? $gateway->slip_discount : 0;
				if ($discount == 0)
				$discount = property_exists( $gateway , 'debit_discount' ) ? $gateway->debit_discount : 0;
				if ($discount == 0)
				$discount = property_exists( $gateway , 'transfer_discount' ) ? $gateway->transfer_discount : 0;

				//Se está na compra, não aplica desconto, valor já está calculado
				if ($order_id <= 0)
				$order_total = $order_total* ( ( 100 - get_valid_value($discount) ) / 100 );	
								
				if ($order_total < $min_value){										
					unset( $available_gateways[$gateway_id] );
				}
			}
			if ( $unset == true ) {
				unset( $available_gateways['sixbank_debit'] );
				unset( $available_gateways['sixbank_slip'] );
				unset( $available_gateways['sixbank_transfer'] );
			}
			return $available_gateways;
		}

	}

	function get_valid_value( $value ) {
		$value = str_replace( '%', '', $value );
		$value = str_replace( ',', '.', $value );

		return $value;
	}

	add_action( 'plugins_loaded', array( 'WC_Sixbank', 'get_instance' ), 0 );

endif;