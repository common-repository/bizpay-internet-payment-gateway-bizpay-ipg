<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           bizpay-internet-payment-gateway-bizpay-ipg
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce BizPay
 * Plugin URI:        http://www.bizpaycentral.com/
 * Description:       WooCommerce BizPay Payment Gateway. Make your online payments via BizPay IPG.
 * Version:           2.0.2
 * Author:            BizPay (Pvt) Ltd
 * Author URI:        http://www.bizpaycentral.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bizpay-internet-payment-gateway-bizpay-ipg
 * Domain Path:       /languages
 */

add_action('plugins_loaded', 'woocommerce_biz_payment_init', 0);

function woocommerce_biz_payment_init()
{
    if (!class_exists('WC_Payment_Gateway')):
        return;
    endif;
    include_once('bizpay.php');
    
    /**
     * Gateway class
     */
    class WC_Biz_Payment_Gateway extends WC_Payment_Gateway
    {
        
        public $bz_pay;
        
        /**
         * Make __construct()
         * */
        public function __construct()
        {
            $this->id                 = 'bizpay'; // ID for WC to associate the gateway values
            $this->method_title       = 'BizPay'; // Gateway Title as seen in Admin Dashboad
            $this->method_description = 'BizPay - Payment Gateway'; // Gateway Description as seen in Admin Dashboad
            $this->has_fields         = false; // Inform WC if any fileds have to be displayed to the visitor in Frontend 
            
            $this->init_form_fields(); // defines your settings to WC
            $this->init_settings(); // loads the Gateway settings into variables for WC
            
            $this->title       = $this->settings['title']; // Title as displayed on Frontend
            $this->description = $this->settings['description']; // Description as displayed on Frontend
            
            $this->merchant       = $this->settings['merchant'];
            $this->apikey         = $this->settings['apikey'];
            $this->apitoken       = $this->settings['apitoken'];
            $this->redirect_page  = $this->settings['redirect_page']; // Define the Redirect Page.
            $this->demomode       = $this->settings['demomode'];
            $this->msg['message'] = '';
            $this->msg['class']   = '';
            
            
            
            add_action('init', array(
                &$this,
                'check_biz_pay_response'
            ));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array(
                $this,
                'check_biz_pay_response'
            )); //update for woocommerce >2.0
            
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    &$this,
                    'process_admin_options'
                )); //update for woocommerce >2.0
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(
                    &$this,
                    'process_admin_options'
                )); // WC-1.6.6
            }
            
            add_action('woocommerce_receipt_bizpay', array(
                &$this,
                'receipt_page'
            ));
        }
        
        /**
         * Initiate Form Fields in the Admin Backend
         */
        function init_form_fields()
        {
            
            $this->form_fields = array(
                // Activate the Gateway
                'enabled' => array(
                    'title' => __('Enable/Disable:', 'woo_bizpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable BizPay', 'woo_bizpay'),
                    'default' => 'no',
                    'description' => 'Show in the Payment List as a payment option'
                ),
                // Title as displayed on Frontend
                'title' => array(
                    'title' => __('Title:', 'woo_bizpay'),
                    'type' => 'text',
                    'default' => __('Online Payments', 'woo_bizpay'),
                    'description' => __('This controls the title which the user sees during checkout.', 'woo_bizpay'),
                    'desc_tip' => true
                ),
                // Description as displayed on Frontend
                'description' => array(
                    'title' => __('Description:', 'woo_bizpay'),
                    'type' => 'textarea',
                    'default' => __('Pay with Visa/MasterCard/Sampath Vishwa / eZCash / mCash with BizPay IPG.', 'woo_bizpay'),
                    'description' => __('This controls the description which the user sees during checkout.', 'woo_bizpay'),
                    'desc_tip' => true
                ),
                // LIVE Key-ID
                'merchant' => array(
                    'title' => __('Merchant:', 'woo_bizpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by BizPay'),
                    'desc_tip' => true
                ),
                // LIVE Key-Secret
                'apikey' => array(
                    'title' => __('API Key:', 'woo_bizpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by BizPay'),
                    'desc_tip' => true
                ),
                // LIVE Key-Secret
                'apitoken' => array(
                    'title' => __('API Token:', 'woo_bizpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by BizPay'),
                    'desc_tip' => true
                ),
                
                'demomode' => array(
                    'title' => __('Demo Mode', 'woo_bizpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable Demo Mode', 'woo_bizpay'),
                    'default' => 'yes'
                ),
                
                
                // Page for Redirecting after Transaction
                'redirect_page' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->bizpay_get_pages('Select Page'),
                    'description' => __('URL of success page', 'woo_bizpay'),
                    'desc_tip' => true
                )
            );
        }
        
        /**
         * Receipt Page
         * */
        function receipt_page($order)
        {
            echo '<p><strong>' . __('Thank you for your order.', 'woo_bizpay') . '</strong><br/>' . __('The payment page will open soon.', 'woo_bizpay') . '</p>';
            echo $this->generate_bizpay_form($order);
        }
        
        function generate_bizpay_form($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            
            // Redirect URL
            if ($this->redirect_page == '' || $this->redirect_page == 0) {
                $redirect_url = get_site_url() . "/";
            } else {
                $redirect_url = get_permalink($this->redirect_page);
            }
            // Redirect URL : For WooCoomerce 2.0
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
            }
            
            $address       = $order->billing_address_1 . ' ' . $order->billing_city . ' ' . $order->billing_country . ' ' . $order->billing_postcode;
            $payment_array = array(
                'merchant' => $this->merchant,
                'apikey' => $this->apikey,
                'apitoken' => $this->apitoken,
                'demomode' => $this->demomode,
                'amount' => $order->order_total,
                'refnumber' => $order_id,
                'description' => $order->order_comments,
                'customer' => $order->billing_first_name . ' ' . $order->billing_last_name,
                'company' => $order->billing_company,
                'address' => $address,
                'mobile' => $order->billing_phone,
                'email' => $order->billing_email,
                'currency' => $order_currency,
                'receipturl' => $redirect_url
            );
            
            
            $bz_pay = new bizpay();
            $bz_pay->pay_request($payment_array);
        }
        
        /**
         * Check for valid gateway server callback
         */
        function check_biz_pay_response()
        {
            global $woocommerce;
            if (isset($_REQUEST['wc-api']) && $_REQUEST['wc-api'] == 'WC_Biz_Payment_Gateway') {
                $bz_pay        = new bizpay();
                $payment_array = array(
                    'merchant' => $this->merchant,
                    'apikey' => $this->apikey,
                    'apitoken' => $this->apitoken,
                    'demomode' => $this->demomode,
                    'token' => $_REQUEST['token'],
                    'approval' => $_REQUEST['approval']
                );
                $response      = $bz_pay->in_response('', $payment_array);
                $order_id      = $response['clientref'];
                if ($order_id != '') {
                    try {
                        $order            = new WC_Order($order_id);
                        $status           = $response['status'];
                        $return_status    = strtolower($status);
                        $ispaid           = $response['ispaid'];
                        $trans_authorised = false;
                        if ($order->status !== 'completed') {
                            if ($ispaid == 'true' && $return_status == 'sucess') {
                                $trans_authorised     = true;
                                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                                $this->msg['class']   = 'woocommerce-message';
                                if ($order->status == 'processing') {
                                    $order->add_order_note('status(' . $response['status'] . ')<br/>Bank Ref: ' . $response['bizpayrefnumber']);
                                } else {
                                    $order->payment_complete();
                                    $order->add_order_note('BizPay payment successful.| (' . $response['message'] . ')<br/> Ref: ' . $response['bizpayrefnumber']);
                                    $woocommerce->cart->empty_cart();
                                }
                            } else if ($ispaid == 'false' && $return_status == 'sucess') {
                                $trans_authorised     = true;
                                $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined. Gateway Response :" . $response['message'];
                                $this->msg['class']   = 'woocommerce-error';
                                
                                $order->add_order_note('BizPay payment declined.<br/>(' . $response['message'] . ')<br/> Ref: ' . $response['bizpayrefnumber']);
                                $order->update_status('failed');
                                // $woocommerce->cart->empty_cart();
                            } else {
                                $this->msg['class']   = 'woocommerce-error';
                                $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order->add_order_note('Transaction ERROR: ' . $response['error'] . '<br/>status(' . $response['status'] . ')<br/>Bank Ref: ' . $response['bizpayrefnumber']);
                            }
                            
                            if ($trans_authorised == false) {
                                $order->update_status('failed');
                            }
                            //removed for WooCommerce 2.0
                            //add_action('the_content', array(&$this, 'payupaisa_showMessage'));
                        }
                    }
                    catch (Exception $e) {
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
                }
                if ($this->redirect_page == '' || $this->redirect_page == 0) {
                    //$redirect_url = $order->get_checkout_payment_url( true );
                    $redirect_url = get_permalink(get_option('woocommerce_myaccount_page_id'));
                } else {
                    $redirect_url = get_permalink($this->redirect_page);
                    
                    if ($redirect_url = get_permalink(get_option('woocommerce_myaccount_page_id'))) {
                        $redirect_url = $redirect_url . "/orders";
                    }
                }
                
                wp_redirect($redirect_url);
            }
        }
        
        /**
         * Process the payment and return the result
         * */
        function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            
            if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) { // For WC 2.1.0
                $checkout_payment_url = $order->get_checkout_payment_url(true);
            } else {
                $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
            }
            
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $checkout_payment_url))
            );
        }
        
        /**
         * Get Page list from WordPress
         * */
        function bizpay_get_pages($title = false, $indent = true)
        {
            $wp_pages  = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title):
                $page_list[] = $title;
            endif;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page  = get_post($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }
        
    }
    
    /**
     * Add the Gateway to WooCommerce
     * */
    function woocommerce_add_biz_payment_gateway($methods)
    {
        $methods[] = 'WC_Biz_Payment_Gateway';
        return $methods;
    }
    
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_biz_payment_gateway');
}

/**
 * 'Settings' link on plugin page
 * */
add_filter('plugin_action_links', 'bizpay_add_action_plugin', 10, 5);

function bizpay_add_action_plugin($actions, $plugin_file)
{
    static $plugin;
    
    if (!isset($plugin))
        $plugin = plugin_basename(__FILE__);
    if ($plugin == $plugin_file) {
        
        $settings = array(
            'settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_biz_payment_gateway">' . __('Settings') . '</a>'
        );
        
        $actions = array_merge($settings, $actions);
    }
    
    return $actions;
}