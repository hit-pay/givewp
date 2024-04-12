=== HitPay Payment Gateway for GiveWP ===
Contributors: HitPay
Tags: hitpay payments, GiveWP, payment gateway, hitpay, pay with hitpay, credit card, paynow, wechatpay, alipay
Requires at least: 4.0
Tested up to: 6.5.2
Stable tag: 1.0.4
Requires PHP: 5.5
GiveWP requires at least: 2.0
GiveWP tested up to: 3.7.0
License: MIT

HitPay Payment Gateway for GiveWP Plugin allows merchants to accept donations via PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments

== Description ==

HitPay Payment Gateway for GiveWP Plugin allows merchants to accept donations via PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments

This plugin would communicate with 3rd party HitPay payment gateway(https://www.hitpayapp.com/) in order to process the payments.

Merchant must create an account with HitPay payment gateway(https://www.hitpayapp.com/).

Pay only per transaction. No monthly, setup, admin or any hidden service fees.

Merchant once created an account with HitPay payment gateway(https://www.hitpayapp.com/), they can go to thier HitPay dashboard and choose the payment options they would to avail for their site.

And merchant need to copy the API keys and Salt values from the HitPay Web Dashboard under Settings > Payment Gateway > API Keys

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'HitPay Payment Gateway for GiveWP'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `hitpay-payment-gateway-for-givewp.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `hitpay-payment-gateway-for-givewp.zip`
2. Extract the `hitpay-payment-gateway-for-givewp` directory to your computer
3. Upload the `hitpay-payment-gateway-for-givewp` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Configuration ==

1. Go to Donations settings
2. Select the "Payment Gateways" tab
3. Activate the payment method (if inactive) in the Option-Based Form Editor by ticking the Enabled checkbox field
4. Set the name you wish to show your users on Checkout (for example: "HitPay or Creditcard") in the label input field
5. Click the link 'HitPay Payment Gateway' under the tabs
6. Copy the API keys and Salt values from the HitPay Web Dashboard under Settings > Payment Gateway > API Keys
7. Click "Save Changes"
9. All done!

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. You can copy the API keys and Salt values from the HitPay Web Dashboard under Settings > Payment Gateway > API Keys.

= Where can I find more documentation on your service? =

You can find more documentation about our service on our [get started](https://hitpay.zendesk.com/hc/en-us/sections/360002421091-About-HitPay) page.
If there's anything else you need that is not covered on those pages, please get in touch with us, we're here to help you!

= Where can I get support? =

The easiest and fastest way is via our live chat on our [website](https://www.hitpayapp.com/) or via our [contact form](https://www.hitpayapp.com/contactus).

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Pay donation with HitPay Payment Gateway.

== Changelog ==

= 1.0.4 =
* Apr 12, 2024
* Added channel parameter to the gateway
* Plugin tested on upto GiveWp 3.7.0
* Plugin tested on WordPress 6.5.2

= 1.0.3 =
* Apr 02, 2024
* Resolved conflict error with hitpay for woocommerce plugin
* Plugin tested on upto GiveWp 3.6.1
* Plugin tested on WordPress 6.4.3

= 1.0.2 =
* Updated to compatible with Visual Form Builder Forms

= 1.0.0 =
* Initial release.
