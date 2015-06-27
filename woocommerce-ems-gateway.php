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

        function __construct() {

            $this->id = 'eMS_ecommerce_gateway_ajakov';  // Unique ID for your gateway. e.g. ‘your_gateway’
            $this->icon = ''; // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
            $this->has_fields = FALSE; // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
            $this->method_title = 'eMS e-commerce payment gateway'; // Title of the payment method shown on the admin page.
            $this->method_description = 'Credit card payments using eMS e-commerce gateway. Using Banca Intesa Serbia\'s card processing service. Payment currency: RSD.'; //– Description for the payment method shown on the admin page.

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');

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
                )
            );
        }

        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));

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
        public function log($message) {
            if ($this->debug) {
                if (empty($this->log)) {
                    $this->log = new WC_Logger();
                }
                $this->log->add('eMS', $message);
            }
        }

    }

}

function woocommerce_ems_gateway_add_class($methods) {
    $methods[] = 'WC_Gateway_eMS';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_ems_gateway_add_class');


add_action('woocommerce_api_ems_callback', 'woocommerce_ems_gateway_callback_handler');

function woocommerce_ems_gateway_callback_handler() {

    // handle EMS callbacks
}
