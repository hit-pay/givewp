<?php

// No direct access is allowed.
if (! defined('ABSPATH')) {
    exit;
}

use HitPay\Client;
use HitPay\Request\CreatePayment;
use HitPay\Response\PaymentStatus;

/**
 * Proceed only, if class Hitpay_Give_Admin_Settings not exists.
 */
if (! class_exists('Hitpay_Give_Admin_Settings')) {

    /**
     * Class Hitpay_Give_Admin_Settings.
     */
    class Hitpay_Give_Admin_Settings
    {
        /**
         * Hitpay_Give_Admin_Settings constructor.
         */
        public function __construct()
        {
            add_action('give_admin_field_hitpay_title', array($this, 'render_hitpay_title'), 10, 2);
            add_action('give_admin_field_hitpay_label', array($this, 'render_hitpay_label'), 10, 2);
            add_filter('give_get_sections_gateways', array($this, 'register_sections'));
            add_action('give_get_settings_gateways', array($this, 'register_settings'));
			add_action('give_view_donation_details_totals_after', array($this, 'admin_order_totals'), 10, 2);
			add_action('give_view_donation_details_update_after', array($this, 'admin_refund_button'), 10, 2);
			add_action('give_updated_edited_donation', array($this, 'give_hitpay_process_refund'), 10, 2 );
        }
		
		public function getMode($userMode)
		{
			$mode = true;
			if ($userMode == 'no') {
				$mode = false;
			}
			return $mode;
		}
		
		public function admin_refund_button( $donationId )
		{
			if (give_get_payment_gateway($donationId) == 'hitpay' && give_is_payment_complete( $donationId )) {
			?>
			<div id="major-publishing-actions">
				<div id="publishing-action">
					<input type="submit" name="hitpay_payment_refund_submit" class="button button-primary right" value="<?php esc_attr_e( 'Refund via HitPay Payment Gateway', 'hitpay-givewp' ); ?>"/>
				</div>
				<div class="clear"></div>
			</div>
			<?php
			}
		}
		
		public function admin_order_totals( $donationId )
		{
			$give_donation = get_post($donationId);
			if (give_get_payment_gateway($donationId) == 'hitpay') {

				$payment_method = '';
				$payment_request_id = give_get_payment_meta($donationId, 'HitPay_payment_request_id', true );

				if (!empty($payment_request_id)) {
					$payment_method = give_get_payment_meta($donationId, 'HitPay_payment_method', true );
					$fees = give_get_payment_meta($donationId, 'HitPay_fees', true );
					if (empty($payment_method) || empty($fees)) {
						
						$give_settings = give_get_settings();
						
						try {
							$hitpay_client = new Client(
								$give_settings['hitpay_api_key'],
								$this->getMode($give_settings['hitpay_mode'])
							);

							$paymentStatus = $hitpay_client->getPaymentStatus($payment_request_id);
							if ($paymentStatus) {
								$payments = $paymentStatus->payments;
								if (isset($payments[0])) {
									$payment = $payments[0];
									$payment_method = $payment->payment_type;
									$fees = $payment->fees;
									give_update_payment_meta($donationId, 'HitPay_payment_method', $payment_method);
									give_update_payment_meta($donationId, 'HitPay_fees', $fees);
								}
							}
						} catch (\Exception $e) {
							$payment_method = $e->getMessage();
						}
					}
				}

				if (!empty($payment_method)) {
					$HitPay_currency = give_update_payment_meta($donationId, 'HitPay_currency', true );
				?>
					<table class="wc-order-totals" style="margin:12px; padding:12px">
						<tbody>
							<tr>
								<td class="label"><?php echo __('HitPay Payment Type', 'hitpay-givewp') ?>:</td>
								<td width="1%"></td>
								<td class="total">
									<span class="woocommerce-Price-amount amount"><bdi><?php echo ucwords(str_replace("_", " ", $payment_method)) ?></bdi></span>
								</td>
							</tr>
							<tr>
								<td class="label"><?php echo __('HitPay Fee', 'hitpay-givewp') ?>:</td>
								<td width="1%"></td>
								<td class="total">
									<span class="woocommerce-Price-amount amount">
										<bdi>
										<?php echo give_currency_filter($fees, $HitPay_currency); ?>
										</bdi>
									</span>
								</td>
							</tr>

						</tbody>
					</table>
				<?php
				}
			}
		}
		
		public function give_hitpay_process_refund($donationId)
		{
			if (isset($_POST['hitpay_payment_refund_submit'])) {
				$amount = give_donation_amount($donationId);
				$amountValue = number_format($amount, 2, '.', '');

				try {
					$HitPay_transaction_id = give_get_payment_meta($donationId, 'HitPay_transaction_id', true );
					$HitPay_is_refunded = give_get_payment_meta($donationId, 'HitPay_is_refunded', true );
					if ($HitPay_is_refunded == 1) {
						throw new Exception(__('Only one refund allowed per transaction by HitPay Payment Gateway.',  'hitpay-givewp'));
					}
					
					$give_settings = give_get_settings();

					$hitpayClient = new Client(
						$give_settings['hitpay_api_key'],
						$this->getMode($give_settings['hitpay_mode'])
					);

					$result = $hitpayClient->refund($HitPay_transaction_id, $amountValue);

					give_update_payment_meta($donationId, 'HitPay_is_refunded', 1);
					give_update_payment_meta($donationId, 'HitPay_refund_id', $result->getId());
					give_update_payment_meta($donationId, 'HitPay_refund_amount_refunded', $result->getAmountRefunded());
					give_update_payment_meta($donationId, 'HitPay_refund_created_at', $result->getCreatedAt());

					$message = __('Refund successful. Refund Reference Id: '.$result->getId().', '
						. 'Payment Id: '.$HitPay_transaction_id.', Amount Refunded: '.$result->getAmountRefunded().', '
						. 'Payment Method: '.$result->getPaymentMethod().', Created At: '.$result->getCreatedAt(), 'hitpay-givewp');

					$totalRefunded = $result->getAmountRefunded();
					if ($totalRefunded) {
						give_update_payment_status($donationId, 'refunded');
						give_insert_payment_note($donationId, $message);
					}

					return;
				} catch (\Exception $e) {
					$message = $e->getMessage().'<br/><br/>';
					$link = admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-payment-details&give-messages[]=payment-updated&id=' . $donationId );
					$message .= '<a href="'.$link.'">Go to Donation Payment Page</a>';
					wp_die(
						$message,
						esc_html__( 'Error', 'hitpay-givewp' ),
						[
							'response' => 400,
						]
					);
				}
			}
		}

        /**
         * Render customized label.
         *
         * @param $field
         * @param $settings
         */
        public function render_hitpay_label($field, $settings)
        {
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field['id']); ?>"><?php echo $field['title']; ?></label>
                </th>
                <td class="give-forminp give-forminp-<?php echo sanitize_title($field['type']) ?>">
                    <span style="<?php echo isset($field['style']) ? $field['style'] :''; ?> font-weight: 700;"><?php echo $field['default']; ?></span>
                </td>
            </tr>
            <?php
        }

        /**
         * Render customized title.
         *
         * @param $field
         * @param $settings
         */
        public function render_hitpay_title($field, $settings)
        {
            $current_tab = give_get_current_setting_tab();

            if ($field['table_html']) {
                echo $field['id'] === "hitpay_module_information" ? '<table class="form-table">' . "\n\n" : '';
            }
            ?>
            <tr valign="top">
                <th scope="row" style="padding: 0px">
                    <div class="give-setting-tab-header give-setting-tab-header-<?php echo $current_tab; ?>">
                        <h2><?php echo $field['title']; ?></h2>
                        <hr>
                    </div>
                </th>
            </tr>
            <?php
        }

        /**
         * Register Admin Settings.
         *
         * @param array $settings List
         *
         * @return array
         */
        function register_settings($settings)
        {
            switch (give_get_current_setting_section()) {
                case 'hitpay':
                    $settings = array(
						array(
                            'id'    => 'hitpay_module_information',
                            'type'  => 'hitpay_title',
                            'title' => __('API Credentials', 'lyra-give')
                        ),
						array(
							'id'      => 'hitpay_mode',
							'name'    => __( 'Live Mode', 'hitpay-give' ),
							'type'    => 'radio_inline',
							'options' => array(
								'yes' => __( 'Live', 'hitpay-give' ),
								'no' => __( 'Sandbox', 'hitpay-give' )
							),
							'default' => 'no',
						),
                        array(
                            'id'      => 'hitpay_api_key',
                            'type'    => 'text',
                            'name'    => __('Api Key', 'hitpay-give'),
                            'desc'    => __('Copy/Paste values from HitPay Dashboard under Payment Gateway > API Keys', 'hitpay-give'),
                            'default' => ''
                        ),
						array(
                            'id'      => 'hitpay_salt',
                            'type'    => 'text',
                            'name'    => __('Salt', 'hitpay-give'),
                            'desc'    => __('Copy/Paste values from HitPay Dashboard under Payment Gateway > API Keys', 'hitpay-give'),
                            'default' => ''
                        ),
						array(
							'id'      => 'hitpay_debug',
							'name'    => __( 'Debug', 'hitpay-give' ),
							'type'    => 'radio_inline',
							'options' => array(
								'yes' => __( 'Enabled', 'hitpay-give' ),
								'no' => __( 'Disabled', 'hitpay-give' )
							),
							'default' => 'no',
						),
                        array(
                            'id'   => 'give_title_hitpay',
                            'type' => 'sectionend'
                        ),
                    );

                    break;
            }

            return $settings;
        }

        /**
         * Register Section for Payment Gateway Settings.
         *
         * @param array $sections List of sections
         *
         * @return mixed
         */
        public function register_sections($sections)
        {
            $sections['hitpay'] = 'HitPay Payment Gateway';

            return $sections;
        }
    }
}

new Hitpay_Give_Admin_Settings();
