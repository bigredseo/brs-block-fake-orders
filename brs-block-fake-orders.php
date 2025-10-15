<?php
/*
Plugin Name: BRS Block Fake Orders
Description: Blocks suspicious checkout/order creation requests (Store API, WC REST, PayPal AJAX) with layered checks + optional required client token header.
Version: 0.1.5.3
Author: Big Red SEO (Conor Treacy)
License: GPLv3
Text Domain: brs-block-fake-orders
*/

defined('ABSPATH') || exit;

/**
 * Basic constants and paths.
 * (Kept minimal here; additional helpers live in includes/common/helpers.php)
 */
if ( ! defined('BRS_BFO_FILE') ) define('BRS_BFO_FILE', __FILE__);
if ( ! defined('BRS_BFO_DIR') )  define('BRS_BFO_DIR', plugin_dir_path(__FILE__));
if ( ! defined('BRS_BFO_URL') )  define('BRS_BFO_URL', plugin_dir_url(__FILE__));

/** PSR-4-ish very light autoloader for our classes. */
spl_autoload_register(function($class){
    // Only load our plugin classes
    if (strpos($class, 'BRS_BFO_') !== 0) return;

    $map = [
        'BRS_BFO_Install'         => 'includes/common/class-brs-bfo-install.php',
        'BRS_BFO_Logger'          => 'includes/common/class-brs-bfo-logger.php',
        'BRS_BFO_Validator'       => 'includes/core/class-brs-bfo-validator.php',
        'BRS_BFO_Assets'          => 'includes/core/class-brs-bfo-assets.php',
        'BRS_BFO_Rest_Hooks'      => 'includes/integrations/class-brs-bfo-rest-hooks.php',
        'BRS_BFO_PayPal_Hooks'    => 'includes/integrations/class-brs-bfo-paypal-hooks.php',
        'BRS_BFO_Classic_Checkout'=> 'includes/integrations/class-brs-bfo-classic-checkout.php',
        'BRS_BFO_Settings'        => 'includes/admin/class-brs-bfo-settings.php',
        'BRS_BFO_Admin_Menu'      => 'includes/admin/class-brs-bfo-admin-menu.php',
    ];

    if (isset($map[$class])) {
        require_once BRS_BFO_DIR . $map[$class];
    }
});

// Small helpers (constants, filters, tiny utils)
require_once BRS_BFO_DIR . 'includes/common/helpers.php';

/** Activation: create/upgrade DB table (moved from main file). */
register_activation_hook(BRS_BFO_FILE, function(){
    (new BRS_BFO_Install())->run();
});

/** Bootstrap instances and register hooks. */
add_action('plugins_loaded', function(){
    // Core services
    $logger    = new BRS_BFO_Logger();      // wraps DB + optional file
    $validator = new BRS_BFO_Validator($logger);

    // Frontend assets
    (new BRS_BFO_Assets())->register();

    // Integrations
    (new BRS_BFO_Rest_Hooks($validator, $logger))->register();
    (new BRS_BFO_PayPal_Hooks($validator, $logger))->register();
    (new BRS_BFO_Classic_Checkout($validator, $logger))->register();

    // Admin
    (new BRS_BFO_Admin_Menu())->register();

    // Admin Settings (includes uninstall option)
    (new BRS_BFO_Settings())->register();
});
