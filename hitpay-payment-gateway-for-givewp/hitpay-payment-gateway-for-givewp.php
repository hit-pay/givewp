<?php
/**
 * Plugin Name: HitPay Payment Gateway for GiveWP
 * Description: HitPay Payment Gateway for GiveWP Plugin allows merchants to accept donations via PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments. You will need a HitPay account, contact support@hitpay.zendesk.com
 * Version: 1.0.0
 * Author: <a href="https://www.hitpayapp.com>HitPay Payment Solutions Pte Ltd</a>
 * Author URI: https://www.hitpayapp.com
 * License: MIT
 * Requires at least: 4.8
 * Tested up to: 6.4.2
 * GiveWP requires at least: 2.0
 * GiveWP tested up to: 3.4.0
 * Requires PHP: 5.5
 */

// No direct access is allowed.
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('HITPAY_GIVE_VERSION')) {
    define('HITPAY_GIVE_VERSION', '1.0.0');
}

if (! defined('HITPAY_GIVE_PLUGIN_URL')) {
    define('HITPAY_GIVE_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (! defined('HITPAY_GIVE_PLUGIN_PATH')) {
    define('HITPAY_GIVE_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

if (! defined('HITPAY_GIVE_FILE')) {
    define('HITPAY_GIVE_FILE', __FILE__);
}

if (! defined('HITPAY_GIVE_BASENAME')) {
    define('HITPAY_GIVE_BASENAME', plugin_basename(HITPAY_GIVE_FILE));
}

if (!class_exists('ComposerAutoloaderInit2a7e2497cc6f77dc9a55e3abf95fd6ad')) {
	require_once HITPAY_GIVE_PLUGIN_PATH . 'vendor/softbuild/hitpay-sdk/src/CurlEmulator.php';
	require_once HITPAY_GIVE_PLUGIN_PATH . 'vendor/autoload.php';
}

require_once HITPAY_GIVE_PLUGIN_PATH . '/includes/admin/hitpay-give-activation.php';
require_once HITPAY_GIVE_PLUGIN_PATH . '/includes/admin/hitpay-give-admin.php';
require_once HITPAY_GIVE_PLUGIN_PATH . '/includes/hitpay-give-payment-gateway.php';

/**
 * Register Payment Gateway.
 *
 * @param  array $gateways
 *
 * @return array
 */
function hitpay_give_register_gateway($gateways)
{
    $gateways['hitpay'] = array(
        'admin_label'    => 'HitPay Payment Gateway for GiveWP',
        'checkout_label' => 'HitPay Payment Gateway'
    );

    return $gateways;
}

add_filter('give_payment_gateways', 'hitpay_give_register_gateway');

function hitpay_give_redirect_notice($form_id)
{
    printf(
        '
        <fieldset class="no-fields">
            <p style="text-align: center;"><b>%1$s</b></p>
        </fieldset>
        ',
        __('You will be redirected to Hitpay Payment Gateway.', 'hitpay-give')
    );

    return true;
}

add_action('give_hitpay_cc_form', 'hitpay_give_redirect_notice');

