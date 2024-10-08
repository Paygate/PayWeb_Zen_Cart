<?php
/**
 * Checkout Process Page
 *
 * Modified for Paygate session issues
 *
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

// This should be first line of the script:
$zco_notifier->notify('NOTIFY_HEADER_START_CHECKOUT_PROCESS');

require DIR_WS_MODULES . zen_get_module_directory('paygatepaywebv3_checkout_process.php');

// load the after_process function from the payment modules
$payment_modules->after_process();

$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET', $insert_id);
$_SESSION['cart']->reset(true);

// unregister session variables used during checkout
unset($_SESSION['sendto']);
unset($_SESSION['billto']);
unset($_SESSION['shipping']);
unset($_SESSION['payment']);
unset($_SESSION['comments']);
$order_total_modules->clear_posts();//ICW ADDED FOR CREDIT CLASS SYSTEM

// This should be before the zen_redirect:
$zco_notifier->notify('NOTIFY_HEADER_END_CHECKOUT_PROCESS');

zen_redirect(
    zen_href_link(
        FILENAME_CHECKOUT_SUCCESS,
        (isset($_GET['action']) && $_GET['action'] == 'confirm' ? 'action=confirm' : ''),
        'SSL'
    )
);

require DIR_WS_INCLUDES . 'application_bottom.php';
