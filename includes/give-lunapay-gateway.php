<?php

if (!defined('ABSPATH')) {
  exit;
}

class Give_lunapay_Gateway {
  static private $instance;

  const QUERY_VAR           = 'lunapay_givewp_return';
  const LISTENER_PASSPHRASE = 'lunapay_givewp_listener_passphrase';

  private function __construct() {
    add_action('init', array($this, 'return_listener'));
    add_action('give_gateway_lunapay', array($this, 'process_payment'));
    add_action('give_lunapay_cc_form', array($this, 'give_lunapay_cc_form'));
    add_filter('give_enabled_payment_gateways', array($this, 'give_filter_lunapay_gateway'), 10, 2);


  }

  static function get_instance() {
    if (null === static::$instance) {
      static::$instance = new static();
    }

    return static::$instance;
  }

  public function give_filter_lunapay_gateway($gateway_list, $form_id) {
    if ((false === strpos($_SERVER['REQUEST_URI'], '/wp-admin/post-new.php?post_type=give_forms'))
      && $form_id
      && !give_is_setting_enabled(give_get_meta($form_id, 'lunapay_customize_lunapay_donations', true, 'global'), array('enabled', 'global'))
    ) {
      unset($gateway_list['lunapay']);
    }
    return $gateway_list;
  }

  private function create_payment($purchase_data) {

    $form_id  = intval($purchase_data['post_data']['give-form-id']);
    $price_id = isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : '';

    // Collect payment data.
    $insert_payment_data = array(
      'price'           => sanitize_text_field($purchase_data['price']),
      'give_form_title' => sanitize_text_field($purchase_data['post_data']['give-form-title']),
      'give_form_id'    => sanitize_text_field($form_id),
      'give_price_id'   => sanitize_text_field($price_id),
      'date'            => sanitize_text_field($purchase_data['date']),
      'user_email'      => sanitize_text_field($purchase_data['user_email']),
      'purchase_key'    => sanitize_text_field($purchase_data['purchase_key']),
      'currency'        => sanitize_text_field(give_get_currency($form_id, $purchase_data)),
      'user_info'       => sanitize_text_field($purchase_data['user_info']),
      'status'          => sanitize_text_field('pending'),
      'gateway'         => sanitize_text_field('lunapay'),
    );

    /**
     * Filter the payment params.
     *
     * @since 3.0.2
     *
     * @param array $insert_payment_data
     */
    $insert_payment_data = apply_filters('give_create_payment', $insert_payment_data);

    // Record the pending payment.
    return give_insert_payment($insert_payment_data);
  }

  private function get_lunapay($purchase_data) {

    $form_id = intval($purchase_data['post_data']['give-form-id']);

    $custom_donation = give_get_meta($form_id, 'lunapay_customize_lunapay_donations', true, 'global');
    $status          = give_is_setting_enabled($custom_donation, 'enabled');
      
    if ($status) {
      return array(
        
        'description'         => give_get_meta($form_id, 'lunapay_description', true, true),
        'client_id'           => give_get_meta($form_id, 'lunapay_client_id',true),
        'lunakey'             => give_get_meta($form_id,  'lunapay_lunakey',true),
        'luna_signature_key'  => give_get_meta($form_id,  'lunapay_luna_signature_key',true),
        'group_id'            => give_get_meta($form_id,  'lunapay_group_id',true),
        'end_point'           => give_get_meta($form_id, 'lunapay_server_end_point', true),
      );
    }
    return array(
     
      'description'         => give_get_option('lunapay_description', true),
      'client_id'           => give_get_option('lunapay_client_id'),
      'lunakey'             => give_get_option('lunapay_lunakey'),
      'luna_signature_key'  => give_get_option('lunapay_luna_signature_key'),
      'group_id'            => give_get_option('lunapay_group_id'),
      'end_point'           => give_get_option('lunapay_server_end_point', true),
    );
  }

  public static function get_listener_url($form_id) {
    $passphrase = get_option(self::LISTENER_PASSPHRASE, false);
    if (!$passphrase) {
      $passphrase = md5(site_url() . time());
      update_option(self::LISTENER_PASSPHRASE, $passphrase);
    }

    $arg = array(
      self::QUERY_VAR => $passphrase,
      'form_id'       => $form_id,
    );
    return add_query_arg($arg, site_url('/'));
  }

  public function process_payment($purchase_data) {

    // Validate nonce.
    give_validate_nonce($purchase_data['gateway_nonce'], 'give-gateway');

    $payment_id = $this->create_payment($purchase_data);

    // Check payment.
    if (empty($payment_id)) {
      // Record the error.
      give_record_gateway_error(__('Payment Error', 'give-lunapay'), sprintf( /* translators: %s: payment data */
        __('Payment creation failed before sending donor to lunapay. Payment data: %s', 'give-lunapay'), json_encode($purchase_data)), $payment_id);
      // Problems? Send back.
      give_send_back_to_checkout();
    }
      

    $form_id     = intval($purchase_data['post_data']['give-form-id']);
    $lunapay_key = $this->get_lunapay($purchase_data);

    $name = $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'];

      $paymentParameter = array(
        'amount' => strval($purchase_data['price']),
        'reference_no' => $payment_id,
        'item'=> substr(trim($lunapay_key['description']), 0, 120),
        'description' => 'Contribution',
        'email' => $purchase_data['user_email'],
        'name' => empty($name) ? $purchase_data['user_email'] : trim($name),
        'callback_url' => self::get_listener_url($form_id),
        'redirect_url' => self::get_listener_url($form_id),
        'cancel_url' => self::get_listener_url($form_id),
        );
	  
	  if(!empty($lunapay_key['group_id'])){
				
		$paymentParameter['group_id'] = $lunapay_key['group_id'];
				
	   }
	  

    $lunapay_parameter = array(
      'client_id' => $lunapay_key['client_id'],
      'lunakey' => $lunapay_key['lunakey'],
      'luna_signature_key' => $lunapay_key['luna_signature_key'],
      'group_id' => $lunapay_key['group_id'],
      'end_point' => $lunapay_key['end_point']);


     
    $connect = new lunapayGiveWPConnect($lunapay_key['end_point']);
    $lunapay = new lunapayGiveAPI($connect);

    $tokenParameter = array(
                'client_id' => $lunapay_key['client_id'],
                'secret_code' => $lunapay_key['lunakey']
            );

    $token = $lunapay->getToken($tokenParameter);
      
      if (!empty($lunapay_key['luna_signature_key'])) {
		  
		  list($payment_url, $rbody) = $lunapay->sentPaymentSecure($token, $paymentParameter);
	  }else{
		  
		  list($payment_url, $rbody) = $lunapay->sentPayment($token, $paymentParameter);
	  }
	  
	  
    

    $body = json_decode($rbody);
      
    if($body->status === 'created'){

    give_update_meta($form_id, 'lunapay_id', $body->payment_id);
    give_update_meta($form_id, 'lunapay_payment_id', $payment_id);

    wp_redirect($body->payment_url);
    exit;

    }else{

      // Record the error.
      give_record_gateway_error(__('Payment Error', 'give-lunapay'), sprintf( /* translators: %s: payment data */
        __('Bill creation failed. Error message: %s', 'give-lunapay'), json_encode($rbody)), $payment_id);
      // Problems? Send back.
      give_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['give-gateway']);



    }

  }

  public function give_lunapay_cc_form($form_id) {
    ob_start();

    //Enable Default CC fields (billing info)
    $post_lunapay_cc_fields       = give_get_meta($form_id, 'lunapay_collect_billing', true);
    $post_billlz_customize_option = give_get_meta($form_id, 'lunapay_customize_lunapay_donations', true, 'global');

    $global_lunapay_cc_fields = give_get_option('lunapay_collect_billing');

    //Output CC Address fields if global option is on and user hasn't elected to customize this form's offline donation options
    if (
      (give_is_setting_enabled($post_billlz_customize_option, 'global') && give_is_setting_enabled($global_lunapay_cc_fields))
      || (give_is_setting_enabled($post_billlz_customize_option, 'enabled') && give_is_setting_enabled($post_lunapay_cc_fields))
    ) {
      give_default_cc_address_fields($form_id);
    }

    echo ob_get_clean();
  }

  private function publish_payment($payment_id_give, $data) {
    
	if ('publish' !== get_post_status($payment_id_give)) {
	  give_update_payment_status($payment_id_give, 'publish');
	  give_insert_payment_note($payment_id_give, "Bill ID: {$data['id']}.");
	}
  }

  public function return_listener() {
      
    if (!isset($_GET[self::QUERY_VAR])) {
      return;
    }

    $passphrase = get_option(self::LISTENER_PASSPHRASE, false);
    if (!$passphrase) {
      return;
    }

    if ($_GET[self::QUERY_VAR] != $passphrase) {
      return;
    }

    if (!isset($_GET['form_id'])) {
      exit;
    }else{
	  $form_id = sanitize_text_field($_GET['form_id']);
	}

	  
	$custom_donation = give_get_meta($form_id, 'lunapay_customize_lunapay_donations', true, 'global');
    $status          = give_is_setting_enabled($custom_donation, 'enabled');
	    
    if ($status) {

	 $client_id = give_get_meta($form_id, 'lunapay_client_id',true);
	 $lunakey = give_get_meta($form_id,  'lunapay_luna_signature_key',true);
	 $end_point = give_get_meta($form_id, 'lunapay_server_end_point', true);
		
    }else{
		
	 $client_id = give_get_option('lunapay_client_id');
	 $lunakey = give_get_option('lunapay_lunakey');
	 $end_point	= give_get_option('lunapay_server_end_point', true);
		
	}
	  
	try {
         $data = lunapayGiveWPConnect::afterpayment();
	} catch (Exception $e) {
		status_header(403);
		exit('Some required parameter not redirect');
	} 
	  
	  
    $payment_id = sanitize_text_field($data['payment_id']);
	  


    $connect = new lunapayGiveWPConnect($end_point);
    $lunapay = new lunapayGiveAPI($connect);
	  
      $tokenParameter = array(
                'client_id' => $client_id,
                'secret_code' => $lunakey
            );
	  
	  
    $token = $lunapay->getToken($tokenParameter);
	  
    list($paymentID, $status) = $lunapay->getPaymentStatus($token, $payment_id);

    $payment_id_give = give_get_meta($form_id, 'lunapay_payment_id', true);
	  
	if ($status == 'Paid') {
        $this->publish_payment($payment_id_give, $data);
    }
	  
	if ($data['type'] === 'redirect') {
		
		if ($status == 'Paid') {
        //give_send_to_success_page();

        $return = add_query_arg(array(
          'payment-confirmation' => 'lunapay',
          'payment-id'           => $payment_id_give,
        ), get_permalink(give_get_option('success_page')));

      } else {
         $return = give_get_failed_transaction_uri('?payment-id=' . $payment_id_give);
      }
		wp_redirect($return);
	}  
    exit;
  }

}
Give_lunapay_Gateway::get_instance();