<?php
namespace sixbank;
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
			add_action( 'admin_init', array($this, 'child_plugin_has_parent_plugin' ));
						
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
				add_filter('woocommerce_billing_fields', array($this, 'custom_woocommerce_billing_fields'), 99);
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
						'callback' => array($this, 'sixbank_order_return_post'),
					) );
				} );
				add_filter( 'user_has_cap', array($this, 'order_pay_without_login'), 9999, 3 );					
				add_filter( 'woocommerce_available_payment_gateways', array( $this, 'sixbank_unset_gateway_subscription' ) );
				add_action( 'plugins_loaded', array($this, 'sixbank_update_db_check' ) );				
				add_action( 'init', array($this, 'register_authorized_order_status' ) );
				add_filter( 'wc_order_statuses', array($this,'add_authorized_to_order_statuses' ) );

				add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tab') , 99 , 1 );
				add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_product_custom_fields' )); 				
				add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_product_custom_fields_save' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

		}

		function woocommerce_product_data_tab( $product_data_tabs ) {
			$product_data_tabs['sixbank_recurrent'] = array(
				'label' => __( 'Recorrência', 'sixbank' ),
				'target' => 'sixbank_recurrent_tab',
			);
			return $product_data_tabs;
		}

		function woocommerce_product_custom_fields()
		{
			global $woocommerce, $post;
			echo '<div id="sixbank_recurrent_tab" class="panel woocommerce_options_panel">';

			woocommerce_wp_select(
				array(
					'id' => 'sixbank_product_recurrent',
					'placeholder' => 'Produto recorrente?',
					'label' => __('Produto recorrente?', 'woocommerce'),
					'desc_tip' => 'true',
					'options' => array(
						'no' => 'Não',
						'yes' => 'Sim',						
					)
				)
			);

			woocommerce_wp_select(
				array(
					'id' => 'sixbank_subscription_period',
					'placeholder' => 'Produto recorrente?',
					'label' => __('Todo(a)?', 'woocommerce'),
					'desc_tip' => 'true',
					'options' => array(
						'day' => 'Dia',
						'week' => 'Semana',
						'month' => 'Mês',
						'year' => 'Ano',
					)
				)
			);
			
			// Custom Product Text Field
			woocommerce_wp_text_input(
				array(
					'id' => 'sixbank_subscription_days',
					'placeholder' => 'Expira após (em dias)',
					'label' => __('Expira após (em dias)', 'woocommerce'),
					'type' => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min' => '0'
					)
				)
			);

			

			//Custom Product Number Field
			woocommerce_wp_text_input(
				array(
					'id' => 'sixbank_subscription_frequency',
					'placeholder' => 'Frequência / a cada',
					'label' => __('Frequência / a cada', 'woocommerce'),
					'type' => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min' => '0'
					)
				)
			);			
			echo '</div>';
		}

		function woocommerce_product_custom_fields_save($post_id)
		{
			$sixbank_product_recurrent = $_POST['sixbank_product_recurrent'];
			if (!empty($sixbank_product_recurrent))
				update_post_meta($post_id, 'sixbank_product_recurrent', esc_attr($sixbank_product_recurrent));
			// Custom Product Text Field
			$sixbank_subscription_period = $_POST['sixbank_subscription_period'];
			if (!empty($sixbank_subscription_period))
				update_post_meta($post_id, 'sixbank_subscription_period', esc_attr($sixbank_subscription_period));
			// Custom Product Number Field
			$sixbank_subscription_days = $_POST['sixbank_subscription_days'];
			if (!empty($sixbank_subscription_days))
				update_post_meta($post_id, 'sixbank_subscription_days', esc_attr($sixbank_subscription_days));
			// Custom Product Textarea Field
			$sixbank_subscription_frequency = $_POST['sixbank_subscription_frequency'];
			if (!empty($sixbank_subscription_frequency))
				update_post_meta($post_id, 'sixbank_subscription_frequency', esc_html($sixbank_subscription_frequency));
		}


		function child_plugin_has_parent_plugin() {
			if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ) {
				include_once dirname( __FILE__ ) . '/includes/views/notices/html-notice-extra-fields-missing.php';
		
				deactivate_plugins( plugin_basename( __FILE__ ) ); 
		
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
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

			$gateway = new \sixbank\payment\WC_Sixbank_Credit_Gateway();
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

		function sixbank_order_return_post($data){
			global $wpdb;
			$tid = $data['TID'];
			$recurrences_id = $data['recurrences_id'];
			$order_ref = $data['order_reference'];
			$status = $data['status'];			
			
			//Busca o pedido pela referencia
			$original_order_id = $order_ref;
			$original_order  = wc_get_order( $order_ref  );

			$gateway = new \sixbank\payment\WC_Sixbank_Credit_Gateway();
			$response = $gateway->report($original_order , $tid);
			//$status = $response->getResponse()['status'];
						
			if (!$original_order ){
				echo json_encode(array('data' => 'Order not found'));
				header('HTTP/1.0 204 Not Found', true, 204);
				die();
			}
			
			//Se for recorrencia, cria um novo pedido
			if ($recurrences_id && in_array($status, [4, 8]) ){
				$order_id = $this->create_order($original_order_id);

				$order = new \WC_Order($order_id);

				//Adiciona o ticket id
				update_post_meta( $order_id, '_ticket_id', $data['ticket_id']);

				$this->duplicate_order_header($original_order_id, $order_id);
				$this->duplicate_billing_fieds($original_order_id, $order_id);
				$this->duplicate_shipping_fieds($original_order_id, $order_id);

				$this->duplicate_line_items($original_order, $order_id);
				$this->duplicate_shipping_items($original_order, $order_id);
				$this->duplicate_coupons($original_order, $order_id);
				$this->duplicate_payment_info($original_order_id, $order_id, $order);
				$order->calculate_taxes();
				$this->add_order_note($original_order_id, $order);
			}else{
				$order = $original_order;			
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
			
			$customer = WC()->session->get('customer');
			$data = WC()->session->get('custom_data');
			
			/*$fields['billing_rg'] = array(
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
			);*/
			
			if( isset($customer['first_name']) && ! empty($customer['first_name']) )
			$fields['billing_first_name']['default'] = $customer['first_name'];

			if( isset($customer['last_name']) && ! empty($customer['last_name']) )
			$fields['billing_last_name']['default'] = $customer['last_name'];

			if( isset($customer['postcode']) && ! empty($customer['postcode']) )
			$fields['billing_postcode']['default'] = $customer['postcode'];

			if( isset($customer['city']) && ! empty($customer['city']) )
			$fields['billing_city']['default'] = $customer['city'];

			if( isset($customer['address']) && ! empty($customer['address']) )
			$fields['billing_address_1']['default'] = $customer['address'];

			if( isset($customer['state']) && ! empty($customer['state']) )
			$fields['billing_state']['default'] = $customer['state'];
			
			if( isset($customer['phone']) && ! empty($customer['phone']) )
			$fields['billing_phone']['default'] = $customer['phone'];

			if( isset($customer['email']) && ! empty($customer['email']) )
			$fields['billing_email']['default'] = $customer['email'];

			if( isset($data['billing_rg']) && ! empty($data['billing_rg']) )
			$fields['billing_rg']['default'] = $data['billing_rg'];
				
			if( isset($data['billing_cpf']) && ! empty($data['billing_cpf']) )
			$fields['billing_cpf']['default'] = $data['billing_cpf'];

			if( isset($data['billing_cnpj']) && ! empty($data['billing_cnpj']) )
			$fields['billing_cnpj']['default'] = $data['billing_cnpj'];

			if( isset($data['billing_persontype']) && ! empty($data['billing_persontype']) )
			$fields['billing_persontype']['default'] = $data['billing_persontype'];

			if( isset($data['billing_birthdate']) && ! empty($data['billing_birthdate']) )
			$fields['billing_birthdate']['default'] = $data['billing_birthdate'];
			
			if( isset($data['billing_sex']) && ! empty($data['billing_sex']) )
			$fields['billing_sex']['default'] = $data['billing_sex'];
					
			if( isset($data['billing_number']) && ! empty($data['billing_number']) )
			$fields['billing_number']['default'] = $data['billing_number'];
					
			
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
			array_push( $methods, 'sixbank\payment\WC_Sixbank_Debit_Gateway', 'sixbank\payment\WC_Sixbank_Credit_Gateway', 'sixbank\payment\WC_Sixbank_Slip_Gateway', 'sixbank\payment\WC_Sixbank_Transfer_Gateway');

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
			if (is_admin()) return $available_gateways;
			
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
					$sixbank_recurrent = get_post_meta($values['product_id'], 'sixbank_product_recurrent', true);
        			if (WC()->session->get('cart_recurrent')){
					//if ($_product->is_type('sixbank_subscription')){
						$unset = true;
					}
				}
				$order_total = WC()->cart->total;;
			}else{
				$order = wc_get_order( $order_id );
				foreach( $order->get_items() as $item_id => $item ){
					//Get the WC_Product object
					$_product = $item->get_product();
					$sixbank_recurrent = get_post_meta($values['product_id'], 'sixbank_product_recurrent', true);
        			if (WC()->session->get('cart_recurrent')){
					//if ($_product->is_type('sixbank_subscription')){
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
							
				
				if ($order_total < floatval($min_value)){										
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

		private function create_order($original_order_id) {
			$new_post_author    = wp_get_current_user();
			$new_post_date      = current_time( 'mysql' );
			$new_post_date_gmt  = get_gmt_from_date( $new_post_date );
	
			$order_data =  array(
				'post_author'   => $new_post_author->ID,
				'post_date'     => $new_post_date,
				'post_date_gmt' => $new_post_date_gmt,
				'post_type'     => 'shop_order',
				'post_title'    => __( 'Recurrent Order', 'woocommerce' ),
				/* 'post_status'   => 'draft', */
				'post_status'   => 'wc-on-hold',
				'ping_status'   => 'closed',
				/* 'post_excerpt'  => 'Duplicate Order based on original order ' . $original_order_id, */
				'post_password' => uniqid( 'order_' ),   // Protects the post just in case
				'post_modified'             => $new_post_date,
				'post_modified_gmt'         => $new_post_date_gmt
			);
	
			$new_post_id = wp_insert_post( $order_data, true );
	
			return $new_post_id;
		}

		private function duplicate_order_header($original_order_id, $order_id) {
			update_post_meta( $order_id, '_order_shipping',         get_post_meta($original_order_id, '_order_shipping', true) );
			update_post_meta( $order_id, '_order_discount',         get_post_meta($original_order_id, '_order_discount', true) );
			update_post_meta( $order_id, '_cart_discount',          get_post_meta($original_order_id, '_cart_discount', true) );
			update_post_meta( $order_id, '_order_tax',              get_post_meta($original_order_id, '_order_tax', true) );
			update_post_meta( $order_id, '_order_shipping_tax',     get_post_meta($original_order_id, '_order_shipping_tax', true) );
			update_post_meta( $order_id, '_order_total',            get_post_meta($original_order_id, '_order_total', true) );
	
			update_post_meta( $order_id, '_order_key',              'wc_' . apply_filters('woocommerce_generate_order_key', uniqid('order_') ) );
			update_post_meta( $order_id, '_customer_user',          get_post_meta($original_order_id, '_customer_user', true) );
			update_post_meta( $order_id, '_order_currency',         get_post_meta($original_order_id, '_order_currency', true) );
			update_post_meta( $order_id, '_prices_include_tax',     get_post_meta($original_order_id, '_prices_include_tax', true) );
			update_post_meta( $order_id, '_customer_ip_address',    get_post_meta($original_order_id, '_customer_ip_address', true) );
			update_post_meta( $order_id, '_customer_user_agent',    get_post_meta($original_order_id, '_customer_user_agent', true) );
		}
	
		private function duplicate_billing_fieds($original_order_id, $order_id) {
			update_post_meta( $order_id, '_billing_city',           get_post_meta($original_order_id, '_billing_city', true));
			update_post_meta( $order_id, '_billing_state',          get_post_meta($original_order_id, '_billing_state', true));
			update_post_meta( $order_id, '_billing_postcode',       get_post_meta($original_order_id, '_billing_postcode', true));
			update_post_meta( $order_id, '_billing_email',          get_post_meta($original_order_id, '_billing_email', true));
			update_post_meta( $order_id, '_billing_phone',          get_post_meta($original_order_id, '_billing_phone', true));
			update_post_meta( $order_id, '_billing_address_1',      get_post_meta($original_order_id, '_billing_address_1', true));
			update_post_meta( $order_id, '_billing_address_2',      get_post_meta($original_order_id, '_billing_address_2', true));
			update_post_meta( $order_id, '_billing_country',        get_post_meta($original_order_id, '_billing_country', true));
			update_post_meta( $order_id, '_billing_first_name',     get_post_meta($original_order_id, '_billing_first_name', true));
			update_post_meta( $order_id, '_billing_last_name',      get_post_meta($original_order_id, '_billing_last_name', true));
			update_post_meta( $order_id, '_billing_company',        get_post_meta($original_order_id, '_billing_company', true));
		}
	
		private function duplicate_shipping_fieds($original_order_id, $order_id) {
			update_post_meta( $order_id, '_shipping_country',       get_post_meta($original_order_id, '_shipping_country', true));
			update_post_meta( $order_id, '_shipping_first_name',    get_post_meta($original_order_id, '_shipping_first_name', true));
			update_post_meta( $order_id, '_shipping_last_name',     get_post_meta($original_order_id, '_shipping_last_name', true));
			update_post_meta( $order_id, '_shipping_company',       get_post_meta($original_order_id, '_shipping_company', true));
			update_post_meta( $order_id, '_shipping_address_1',     get_post_meta($original_order_id, '_shipping_address_1', true));
			update_post_meta( $order_id, '_shipping_address_2',     get_post_meta($original_order_id, '_shipping_address_2', true));
			update_post_meta( $order_id, '_shipping_city',          get_post_meta($original_order_id, '_shipping_city', true));
			update_post_meta( $order_id, '_shipping_state',         get_post_meta($original_order_id, '_shipping_state', true));
			update_post_meta( $order_id, '_shipping_postcode',      get_post_meta($original_order_id, '_shipping_postcode', true));
		}
	
	  // TODO: same as duplicating order from user side
		private function duplicate_line_items($original_order, $order_id) {
			foreach($original_order->get_items() as $originalOrderItem){
				$itemName = $originalOrderItem['name'];
				$qty = $originalOrderItem['qty'];
				$lineTotal = $originalOrderItem['line_total'];
				$lineTax = $originalOrderItem['line_tax'];
				$productID = $originalOrderItem['product_id'];
	
				$item_id = wc_add_order_item( $order_id, array(
						'order_item_name'       => $itemName,
						'order_item_type'       => 'line_item'
				) );
	
				wc_add_order_item_meta( $item_id, '_qty', $qty );
		  // TODO: Is it ok to uncomment this?
				wc_add_order_item_meta( $item_id, '_tax_class', $originalOrderItem['tax_class'] );
				wc_add_order_item_meta( $item_id, '_product_id', $productID );
		  // TODO: Is it ok to uncomment this?
				wc_add_order_item_meta( $item_id, '_variation_id', $originalOrderItem['variation_id'] );
				wc_add_order_item_meta( $item_id, '_line_subtotal', wc_format_decimal( $lineTotal ) );
				wc_add_order_item_meta( $item_id, '_line_total', wc_format_decimal( $lineTotal ) );
				/* wc_add_order_item_meta( $item_id, '_line_tax', wc_format_decimal( '0' ) ); */
				wc_add_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $lineTax ) );
		  // TODO: Is it ok to uncomment this?
				/* wc_add_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( '0' ) ); */
				wc_add_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $originalOrderItem['line_subtotal_tax'] ) );
			}
	
		// TODO This is what is in order_again of class-wc-form-handler.php  
		// Can it be reused or refactored into own function?
		//
			// Copy products from the order to the cart
			/* foreach ( $order->get_items() as $item ) { */
			/* 	// Load all product info including variation data */
			/* 	$product_id   = (int) apply_filters( 'woocommerce_add_to_cart_product_id', $item['product_id'] ); */
			/* 	$quantity     = (int) $item['qty']; */
			/* 	$variation_id = (int) $item['variation_id']; */
			/* 	$variations   = array(); */
			/* 	$cart_item_data = apply_filters( 'woocommerce_order_again_cart_item_data', array(), $item, $order ); */
	
			/* 	foreach ( $item['item_meta'] as $meta_name => $meta_value ) { */
			/* 		if ( taxonomy_is_product_attribute( $meta_name ) ) { */
			/* 			$variations[ $meta_name ] = $meta_value[0]; */
			/* 		} elseif ( meta_is_product_attribute( $meta_name, $meta_value[0], $product_id ) ) { */
			/* 			$variations[ $meta_name ] = $meta_value[0]; */
			/* 		} */
			/* 	} */
		}
	
		private function duplicate_shipping_items($original_order, $order_id) {
			$original_order_shipping_items = $original_order->get_items('shipping');
	
			foreach ( $original_order_shipping_items as $original_order_shipping_item ) {
				$item_id = wc_add_order_item( $order_id, array(
					'order_item_name'       => $original_order_shipping_item['name'],
					'order_item_type'       => 'shipping'
				) );
				if ( $item_id ) {
					wc_add_order_item_meta( $item_id, 'method_id', $original_order_shipping_item['method_id'] );
					wc_add_order_item_meta( $item_id, 'cost', wc_format_decimal( $original_order_shipping_item['cost'] ) );
	
			// TODO: Does not store the shipping taxes
					/* wc_add_order_item_meta( $item_id, 'taxes', $original_order_shipping_item['taxes'] ); */
				}
			}
		}
	
		private function duplicate_coupons($original_order, $order_id) {
			$original_order_coupons = $original_order->get_items('coupon');
			foreach ( $original_order_coupons as $original_order_coupon ) {
				$item_id = wc_add_order_item( $order_id, array(
					'order_item_name'       => $original_order_coupon['name'],
					'order_item_type'       => 'coupon'
				) );
				// Add line item meta
				if ( $item_id ) {
					wc_add_order_item_meta( $item_id, 'discount_amount', $original_order_coupon['discount_amount'] );
				}
			}
		}
	
		private function duplicate_payment_info($original_order_id, $order_id, $order) {
			update_post_meta( $order_id, '_payment_method',         get_post_meta($original_order_id, '_payment_method', true) );
			update_post_meta( $order_id, '_payment_method_title',   get_post_meta($original_order_id, '_payment_method_title', true) );
			/* update_post_meta( $order->id, 'Transaction ID',         get_post_meta($original_order_id, 'Transaction ID', true) ); */
			/* $order->payment_complete(); */
		}
	
		private function add_order_note($original_order_id, $order) {
			$updateNote = 'This order was duplicated from order ' . $original_order_id . '.';
			/* $order->update_status('processing'); */
			$order->add_order_note($updateNote);
		}

	}

	function get_valid_value( $value ) {
		$value = str_replace( '%', '', $value );
		$value = str_replace( ',', '.', $value );

		return $value;
	}

	add_action( 'plugins_loaded', array( 'sixbank\WC_Sixbank', 'get_instance' ), 0 );

endif;
