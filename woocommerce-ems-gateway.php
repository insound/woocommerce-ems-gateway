<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Plugin Name: eMS e-commerce - WooCommerce Gateway
 * Plugin URI: http://aleksandarjakovljevic.com/eMS-woocommerce
 * Description: Extends WooCommerce by adding the eMS e-commerce payment gateway.
 * Version: 0.01
 * Author: Aleksandar Jakovljevic
 * Author URI: http://aleksandarjakovljevic.com/
 */

add_action('plugins_loaded', 'woocommerce_ems_gateway_init');

function woocommerce_ems_gateway_init() {

    class WC_Gateway_eMS extends WC_Payment_Gateway {
        
        
        /** @var boolean Whether or not logging is enabled */
        public static $log_enabled = true;

        /** @var WC_Logger Logger instance */
        public static $logger = false;
        
        public $title;
        public $description;
        public $store_name;
        public $store_id;
        public $store_key;
        public $store_language;

        function __construct() {

            $this->id = 'eMS_ecommerce_gateway_ajakov';  // Unique ID for your gateway. e.g. ‘your_gateway’
            $this->icon = ''; // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
            $this->has_fields = FALSE; // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
            $this->method_title = 'eMS e-commerce payment gateway'; // Title of the payment method shown on the admin page.
            $this->method_description = 'Credit card payments using eMS e-commerce gateway. Using Banca Intesa Serbia\'s card processing service. Payment currency: RSD.'; //– Description for the payment method shown on the admin page.

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->store_name = $this->get_option('store_name');
            $this->store_id = $this->get_option('store_id');
            $this->store_key = $this->get_option('store_key');
            $this->store_language = $this->get_option('store_language');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable eMS Card Payment', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('eMS Card Payment', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Customer Message', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => ''
                ),
                'store_name' => array(
                    'title' => __('Store Name', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This should match your store name from eMS merchant portal', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'store_id' => array(
                    'title' => __('Store ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This should match your store ID from eMS merchant portal', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'store_key' => array(
                    'title' => __('Store Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This should match your store key from eMS merchant portal', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'store_language' => array(
                    'title' => __('Store Language', 'woocommerce'),
                    'description' => __('Pick a language that your store is using', 'woocommerce'),
                    'type' => 'select',
                    'default' => 'SRB',
                    'options' => array(
                         'SRB' => __('Serbian'),
                         'ENU' => __('English'),
                    ), 
               )
            );
        }

        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            
            $redirect_url = $this->send_order_request($order);
            
            self::log(print_r($redirect_url, 1));

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __('Awaiting payment', 'woocommerce'));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        /**
                * Logging method
                * @param  string $message
                */
        public static function log( $message ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$logger ) ) {
                    self::$logger = new WC_Logger();
                }
                self::$logger->add( 'ems', $message );
            }
        }
        
        /**
                * Sending order request to eMS service
                * @param WC_Order $order
                */
        private function send_order_request($order) {
            
            require_once('eMSLib/eMSCheckoutAPI.php');
            
            $ems_cart = new eMSCart($order->get_order_number(), $this->store_language);
            
            $this->add_items_to_ems_cart($order, $ems_cart);
            $ems_store = new eMSCartMerchantInfo($this->store_name, $this->store_id);
            $ems_cart->SetMerchantInfo($ems_store);
            
            
            

            $ime_i_prezime = "Petar Petrovic"; /* ime i prezime primaoca posiljke. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $ulica_i_broj = "Neznanog Junaka 1";/*  ulica i broj primaoca posiljke. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $grad = "Beograd";/* naziv grada primaoca .Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $postanski_broj = "11000";/* Sifra odredisne poste primaoca. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $drzava = "Srbija";/* Drzava primaoca. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $ukupna_cena_isporuke = 0; /* Cena isporuke za korpu. Ukoliko ne naplacujete isporuku postavite ovde iznos 0.00 */
            $podaci_o_isporuci = new eMSCartShippingInfo($ime_i_prezime,$ulica_i_broj,$grad,$postanski_broj,$drzava);
            $podaci_o_isporuci->SetShippingPrice($ukupna_cena_isporuke);

            /* Postvite informacije o isporuci u eMS korpu */
            $ems_cart->SetShippingInfo($podaci_o_isporuci);

            $ime = "Milan"; /* Ime lica koje kupuje. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $prezime = "Petrovic"; /* Prezime lica koje kupuje. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $email = "milanpetrovic@nekidomen.com"; /* Adresa el. poste lica koje kupuje. Zamenite ovu vrednost sa onim sto je kupac popunio u vasem sistemu */
            $podaci_o_kupcu = new eMSCartBillingInfo($ime,$prezime,$email);

             /* Postvite informacije o kupcu u ems korpu */
            $ems_cart->SetBillingInfo($podaci_o_kupcu);

            
            
            $ems_cart->SetShippingTotalAmount($ukupna_cena_isporuke);
            $ems_cart->SetItemTotalAmount($order->get_total());
            $ems_cart->SetDiscountTotalAmount(0);
            $ems_cart->SetTaxTotalAmount(0);
            $ems_cart->SetTotalAmountToPay($order->get_total());



            self::log($this->store_key);
            $ems_cart->SignCartSimple($this->store_key);


            $ems_checkout_response = $ems_cart->CheckoutServer2Server();

            return $ems_checkout_response;

        }
        
        /**
                * 
                * @param WC_Order $order
                * @param eMSCart $ems_cart
                */
        private function add_items_to_ems_cart($order, &$ems_cart) {
            
            $items = $order->get_items();                        
            foreach ($items as $item) {                                
                $ems_cart_item = new eMSCartItem($item['name'], $item['product_id'], $item['qty'], $item['line_subtotal'], $item['line_tax'], $item['line_subtotal_tax'], $item['line_total']);
                $ems_cart->AddCartItem($ems_cart_item);
            }
                            
        }
       
    }

}


add_filter('woocommerce_payment_gateways', 'woocommerce_ems_gateway_add_class');
function woocommerce_ems_gateway_add_class($methods) {
    $methods[] = 'WC_Gateway_eMS';
    return $methods;
}



add_action('woocommerce_api_ems_callback', 'woocommerce_ems_gateway_callback_handler');
function woocommerce_ems_gateway_callback_handler() {
    WC_Gateway_eMS::log(print_r($_REQUEST, 1));
    // handle EMS callbacks
}
