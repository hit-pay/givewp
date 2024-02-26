<?php

// No direct access is allowed.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payment Gateway Activation Banner.
 *
 * Includes and initializes Give activation banner class.
 *
 */
function hitpay_give_activation_banner()
{
    // Check if give plugin is activated or not.
    $is_give_active = defined('GIVE_PLUGIN_BASENAME') ? is_plugin_active(GIVE_PLUGIN_BASENAME) : false;

    // Check if Give is deactivate and show a banner.
    if (current_user_can('activate_plugins') && ! $is_give_active) {
        add_action('admin_notices', 'hitpay_give_activation_notice');

        // Don't let this plugin activate.
        deactivate_plugins(HITPAY_GIVE_BASENAME);

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        return false;
    }

    // Check for activation banner inclusion.
    if (! class_exists('Give_Addon_Activation_Banner') && file_exists(GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php')) {
        include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
    }

    // Initialize activation welcome banner.
    if (class_exists('Give_Addon_Activation_Banner')) {
        $args = array(
            'file'         => __FILE__,
            'name'         => sprintf(__('%s Payment Gateway', 'hitpay-give'), 'HitPay Payment Gateway'),
            'version'      => HITPAY_GIVE_VERSION,
            'settings_url' => admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=hitpay'),
            'testing'      => false
        );

        new Give_Addon_Activation_Banner($args);
    }

    return false;
}

add_action('admin_init', 'hitpay_give_activation_banner');

/**
 * Notice for Activation.
 */
function hitpay_give_activation_notice()
{
    echo '<div class="error">
            <p>'
              . sprintf(__('<strong>Activation Error:</strong> You must have the <a href="https://givewp.com/" target="_blank">Give</a> plugin installed and activated for the %s add-on to activate.', 'hitpay-give'), 'HitPay Payment Gateway') .
            '</p>
         </div>';
}

/**
 * Payment gateway row action links.
 *
 * @param array $actions An array of plugin action links
 *
 * @return array An array of updated action links
 */
function hitpay_give_plugin_action_links($actions)
{
    $new_actions = array(
        'settings' => sprintf(
            '<a href="%1$s">%2$s</a>',
            admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=hitpay'),
            __('Settings', 'hitpay-give')
        )
    );

    return array_merge($new_actions, $actions);
}

add_filter('plugin_action_links_' . HITPAY_GIVE_BASENAME, 'hitpay_give_plugin_action_links');
