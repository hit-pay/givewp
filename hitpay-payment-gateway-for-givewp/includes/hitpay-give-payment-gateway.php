<?php
// No direct access is allowed.
if (! defined('ABSPATH')) {
    exit;
}

use HitPay\Client;
use HitPay\Request\CreatePayment;
use HitPay\Response\PaymentStatus;

if (! class_exists('Hitpay_Give_Gateway_Processor')) {

    /**
     * Handles payment gateway.
     *
     * Adds frontend fields and handles payment processing.
     */
    class Hitpay_Give_Gateway_Processor
    {
		private $debug;
		
        public function __construct()
        {
            add_action('give_gateway_hitpay', array($this, 'hitpay_give_process_payment'));
            add_action('give_handle_hitpay_response', array($this, 'hitpay_give_payment_listener'));

			$this->debug = 'no';
        }

        private function hitpay_give_create_payment($payment_data)
        {
            if (is_array($payment_data) && ! empty($payment_data) && $payment_data != null) {
                $form_id  = isset($payment_data['post_data']) ? intval($payment_data['post_data']['give-form-id']) : '';
                $price_id = isset($payment_data['post_data']['give-price-id']) ? $payment_data['post_data']['give-price-id'] : '';

                $insert_payment_data = array(
                    'price'           => isset($payment_data['price']) ? $payment_data['price'] : '',
                    'give_form_title' => isset($payment_data['post_data']['give-form-title']) ? $payment_data['post_data']['give-form-title'] : '',
                    'give_form_id'    => $form_id,
                    'give_price_id'   => $price_id,
                    'date'            => isset($payment_data['date']) ? $payment_data['date'] : '',
                    'user_email'      => isset($payment_data['user_email']) ? $payment_data['user_email'] : '',
                    'purchase_key'    => isset($payment_data['purchase_key']) ? $payment_data['purchase_key'] :  '',
                    'currency'        => give_get_currency($form_id, $payment_data),
                    'user_info'       => isset($payment_data['user_info']) ? $payment_data['user_info'] : '',
                    'status'          => 'pending',
                    'gateway'         => 'hitpay'
                );

                /**
                 * Filter the payment params.
                 *
                 * @param array $insert_payment_data
                 */
                $insert_payment_data = apply_filters('give_create_payment', $insert_payment_data);

                return give_insert_payment($insert_payment_data);
            }

            return null;
        }
		
		public function getMode($userMode)
		{
			$mode = true;
			if ($userMode == 'no') {
				$mode = false;
			}
			return $mode;
		}
		
		public function getSiteName()
		{   global $blog_id;

			if (is_multisite()) {
				$path = get_blog_option($blog_id, 'blogname');
			} else{
			  $path = get_option('blogname');
			}
			return $path.' - GiveWP';
		}

        public function hitpay_give_process_payment($payment_data)
        {
            $payment_id = $this->hitpay_give_create_payment($payment_data);
			
			// Get give gateways settings.
            $give_settings = give_get_settings();
			$this->debug = $give_settings['hitpay_debug'];

            $this->log('Creating payment with ID#' . $payment_id . '.');

            if (empty($payment_id)) {
                // Save payment creation error to database.
                give_record_gateway_error(
                    'HitPay Payment Gateway payment error',
                     sprintf(
                         'Payment creation failed before sending donor to HitPay Payment Gateway. Payment data: %s',
                         json_encode($payment_data)
                    )
                );

                $this->log('Payment creation failed before sending donor to HitPay Payment Gateway. Payment data:' . print_r($payment_data, true));

                give_send_back_to_checkout();
            }

            $this->log('Generating payment form for donation #' . $payment_id . '.');

			try {
				$hitpay_client = new Client(
					$give_settings['hitpay_api_key'],
					$this->getMode($give_settings['hitpay_mode'])
				);
				
				$redirect_url = home_url('?give-action=handle_hitpay_response&hitpayreturn=1&hitpay_order_id='.$payment_id);
				$webhook = home_url('?give-action=handle_hitpay_response&hitpaywebhook=1&hitpay_order_id='.$payment_id);

				$form_id = isset($payment_data['post_data']['give-form-id']) ? $payment_data['post_data']['give-form-id'] : '';
				$currency_code = give_get_currency($form_id);
				$amount = give_donation_amount($payment_id);

				$create_payment_request = new CreatePayment();
				$create_payment_request->setAmount($amount)
					->setCurrency($currency_code)
					->setReferenceNumber($payment_id)
					->setWebhook($webhook)
					->setRedirectUrl($redirect_url);
					
				$cust_first_name = isset($payment_data['user_info']['first_name']) ? $payment_data['user_info']['first_name'] : '';
				$cust_last_name  = isset($payment_data['user_info']['last_name']) ? $payment_data['user_info']['last_name'] : '';
				$cust_email = isset($payment_data['user_info']['email']) ? $payment_data['user_info']['email'] : '';
				

				$create_payment_request->setName($cust_first_name . ' ' . $cust_last_name);
				$create_payment_request->setEmail($cust_email);

				$create_payment_request->setPurpose($this->getSiteName());
				
				$this->log('Request:');
				$this->log((array)$create_payment_request);

				$result = $hitpay_client->createPayment($create_payment_request);

				$this->log('Response:');
				$this->log((array)$result);
				
				give_update_payment_meta($payment_id, 'HitPay_payment_id', $result->getId());
				
				if ($result->getStatus() == 'pending') {
					wp_redirect($result->getUrl());
					give_die();
				} else {
					throw new Exception(sprintf(__('HitPay: sent status is %s', 'hitpay-givewp'), $result->getStatus()));
				}
			} catch (\Exception $e) {
				$log_message = $e->getMessage();
				$this->log($log_message);

				$status_message = __('HitPay: Something went wrong, please contact the merchant', 'hitpay-givewp');
				give_record_gateway_error(
                    'HitPay Payment Gateway payment error',
                    $status_message
                );
                give_send_back_to_checkout();
			}
        }
		
		

        /**
         * Payment listener.
         *
         * Waits for responses from payment gateway.
         */
        public function hitpay_give_payment_listener()
        {
			if (isset($_GET['hitpayreturn'])) {
				$this->return_from_hitpay();
			} else {
				$this->web_hook_handler();
			}
		}
		
		public function return_from_hitpay()
		{	$give_settings = give_get_settings();
			$this->debug = $give_settings['hitpay_debug'];
			
			if (!isset($_GET['hitpay_order_id'])) {
				$this->log('return_from_hitpay order_id check failed');
				exit;
			}

			$donationId = (int)sanitize_text_field($_GET['hitpay_order_id']);
			$give_donation = get_post($donationId);

			if (isset($_GET['status'])) {
				$status = sanitize_text_field($_GET['status']);
				$reference = sanitize_text_field($_GET['reference']);

				if ($status == 'canceled') {
					$status_message = __('Order cancelled by HitPay.', 'hitpay-givewp').($reference ? ' Reference: '.$reference:'');
					give_update_payment_status($donationId, 'cancelled');
					give_record_gateway_error(
						'HitPay Payment Gateway Error',
						$status_message
					);
					$this->log($status_message);
					give_insert_payment_note($donationId, $status_message);
					$this->send_back_to_checkout($donationId);
				}

				if ($status == 'completed') {
					give_send_to_success_page();
				}
			}
		}

		public function web_hook_handler() 
		{
			$give_settings = give_get_settings();
			$this->debug = $give_settings['hitpay_debug'];
			
			$this->log('Webhook Triggers');
			$this->log('Post Data:');
			$this->log($_POST);

			if (!isset($_GET['hitpay_order_id']) || !isset($_POST['hmac'])) {
				$this->log('order_id + hmac check failed');
				exit;
			}

			$donationId = (int)sanitize_text_field($_GET['hitpay_order_id']);
			$give_donation = get_post($donationId);

			try {
				$data = $_POST;
				unset($data['hmac']);

				$salt = $give_settings['hitpay_salt'];
				if (Client::generateSignatureArray($salt, $data) == $_POST['hmac']) {
					$this->log('hmac check passed');

					$HitPay_is_paid = give_get_payment_meta($donationId, 'HitPay_is_paid', true );

					if (!$HitPay_is_paid) {
						$status = sanitize_text_field($_POST['status']);
						$amount = give_donation_amount($donationId);

						if ($status == 'completed'
							&& $amount == $_POST['amount']
						) {
							$payment_id = sanitize_text_field($_POST['payment_id']);
							$payment_request_id = sanitize_text_field($_POST['payment_request_id']);
							$hitpay_currency = sanitize_text_field($_POST['currency']);
							$hitpay_amount = sanitize_text_field($_POST['amount']);

							if ($give_donation->post_status === 'pending') {
								give_update_payment_status($donationId, 'publish');
								$status_message = 'Payment successful. Transaction Id: '.$payment_id;
								$this->log($status_message);
								give_insert_payment_note($donationId, $status_message);
								give_set_payment_transaction_id($donationId, $payment_id);
								
								give_update_payment_meta($donationId, 'HitPay_transaction_id', $payment_id);
								give_update_payment_meta($donationId, 'HitPay_payment_request_id', $payment_request_id);
								give_update_payment_meta($donationId, 'HitPay_is_paid', 1);
								give_update_payment_meta($donationId, 'HitPay_currency', $hitpay_currency);
								give_update_payment_meta($donationId, 'HitPay_amount', $hitpay_amount);
								give_update_payment_meta($donationId, 'HitPay_WHS',  $status);
							}
						} elseif ($status == 'failed') {
							$payment_id = sanitize_text_field($_POST['payment_id']);
							$hitpay_currency = sanitize_text_field($_POST['currency']);
							$hitpay_amount = sanitize_text_field($_POST['amount']);
							
							give_update_payment_status($donationId, 'failed');
							$status_message = 'Payment Failed. Transaction Id: '.$payment_id;
							$this->log($status_message);
							give_insert_payment_note($donationId, $status_message);
							give_set_payment_transaction_id($donationId, $payment_id);
							
							give_update_payment_meta($donationId, 'HitPay_transaction_id', $payment_id);
							give_update_payment_meta($donationId, 'HitPay_is_paid', 0);
							give_update_payment_meta($donationId, 'HitPay_currency', $hitpay_currency);
							give_update_payment_meta($donationId, 'HitPay_amount', $hitpay_amount);
							give_update_payment_meta($donationId, 'HitPay_WHS',  $status);

						} elseif ($status == 'pending') {
							$payment_id = sanitize_text_field($_POST['payment_id']);
							$hitpay_currency = sanitize_text_field($_POST['currency']);
							$hitpay_amount = sanitize_text_field($_POST['amount']);
							
							$status_message = 'Payment is pending. Transaction Id: '.$payment_id;
							$this->log($status_message);
							give_insert_payment_note($donationId, $status_message);
							give_set_payment_transaction_id($donationId, $payment_id);

							give_update_payment_meta($donationId, 'HitPay_transaction_id', $payment_id);
							give_update_payment_meta($donationId, 'HitPay_is_paid', 0);
							give_update_payment_meta($donationId, 'HitPay_currency', $hitpay_currency);
							give_update_payment_meta($donationId, 'HitPay_amount', $hitpay_amount);
							give_update_payment_meta($donationId, 'HitPay_WHS',  $status);
							
						} else {
							$payment_id = sanitize_text_field($_POST['payment_id']);
							$hitpay_currency = sanitize_text_field($_POST['currency']);
							$hitpay_amount = sanitize_text_field($_POST['amount']);
							
							give_update_payment_status($donationId, 'failed');
							$status_message = 'Payment returned unknown status. Transaction Id: '.$payment_id;
							$this->log($status_message);
							give_insert_payment_note($donationId, $status_message);
							give_set_payment_transaction_id($donationId, $payment_id);

							give_update_payment_meta($donationId, 'HitPay_transaction_id', $payment_id);
							give_update_payment_meta($donationId, 'HitPay_is_paid', 0);
							give_update_payment_meta($donationId, 'HitPay_currency', $hitpay_currency);
							give_update_payment_meta($donationId, 'HitPay_amount', $hitpay_amount);
							give_update_payment_meta($donationId, 'HitPay_WHS',  $status);
						}
					}
				} else {
					throw new \Exception('HitPay: hmac is not the same like generated');
				}
			} catch (\Exception $e) {
				$this->log('Webhook Catch');
				$this->log('Exception:'.$e->getMessage());

				give_update_payment_meta($donationId, 'HitPay_WHS',  $status);
			}
			exit;
		}

        private function send_back_to_checkout($donationId)
        {
            $this->log('send back to checkout: ' . $donationId);
            $_POST['give-current-url'] = get_post_meta($donationId, '_give_current_url', true);
            $_POST['give-form-id'] = get_post_meta($donationId, '_give_payment_form_id', true);
            $_POST['give-price-id'] = get_post_meta($donationId, '_give_payment_price_id', true);

            give_send_back_to_checkout();
        }
		
		private function log($content)
		{
			$debug = $this->debug;
			if ($debug == 'yes') {
				$file = HITPAY_GIVE_PLUGIN_PATH.'debug.log';
				try {
					$fp = fopen($file, 'a+');
					if ($fp) {
						fwrite($fp, "\n");
						fwrite($fp, date("Y-m-d H:i:s").": ");
						fwrite($fp, print_r($content, true));
						fclose($fp);
					}
				} catch (\Exception $e) {}
			}
		}
    }

    return new Hitpay_Give_Gateway_Processor();
}
