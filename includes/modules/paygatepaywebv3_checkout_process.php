<?php
/**
 * Module to process a completed checkout
 *
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if ( ! defined('IS_ADMIN_FLAG')) {
    // phpcs:disable
    die('Illegal Access');
    // phpcs:enable
}
global $zco_notifier;
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEGIN');

require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

global $db, $currencies;

if ( ! defined('TABLE_PAYGATEPAYWEBV3')) {
    // phpcs:disable
    define('TABLE_PAYGATEPAYWEBV3', DB_PREFIX . 'paygatepaywebv3');
    // phpcs:enable
}

if (isset($_POST) && isset($_POST['PAY_REQUEST_ID'])) {
    // Restore session from database
    $sql = "select session_data from " . TABLE_PAYGATEPAYWEBV3;
    $sql .= " where pay_request_id = :pay_request_id ";
    $sql = $db->bindVars($sql, ':pay_request_id', $_POST['PAY_REQUEST_ID'], 'string');

    $result   = $db->Execute($sql);
    $session  = $result->fields['session_data'];
    $_SESSION = unserialize($session);

    // load selected payment module
    include DIR_WS_CLASSES . 'payment.php';
    $payment_modules = new payment($_SESSION['payment']);

    include DIR_WS_CLASSES . 'order.php';
    $order = new order;

    // load the selected shipping module
    include DIR_WS_CLASSES . 'shipping.php';
    $shipping_modules = new shipping($_SESSION['shipping']);

    // prevent 0-entry orders from being generated/spoofed
    if (sizeof($order->products) < 1) {
        zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
    }

    // Check if this payrequest id has already been processed
    $sql      = "select * from " . TABLE_PAYGATEPAYWEBV3;
    $sql      .= " where pay_request_id = :pay_request_id";
    $sql      = $db->bindVars($sql, ':pay_request_id', $_POST['PAY_REQUEST_ID'], 'string');
    $requests = $db->Execute($sql);
    $request  = [];
    foreach ($requests as $req) {
        $request[] = $req;
    }

    if ((int)$request[0]['orders_id'] !== 0) {
        // Order already exists - check status
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS));
    } else {
        include DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new order_total;
        $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS');
        $order_totals = $order_total_modules->process();
        $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS');


        $pw3 = new PaygatePaywebV3();

        $processThisOrder = false;

        $validRedirectMethod = ! $pw3->getUseipn();
        $validNotifyMethod   = ! isset($_GET['uuid']) && $pw3->getUseipn();

        if ($validRedirectMethod || $validNotifyMethod) {
            $processThisOrder = true;
        }

        if ($processThisOrder) {
            // Now process order based on response
            $pw3->before_process();
            $order_status = $pw3->getOrderStatus();
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_BEFOREPROCESS');

            // create the order record
            $insert_id = $order->create($order_totals, 2);

            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE');

            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_AFTER_ORDER_CREATE');
            // store the product info to the order
            $order->create_add_products($insert_id);
            $_SESSION['order_number_created'] = $insert_id;
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS');
            //send email notifications
            $order->send_order_email($insert_id, 2);
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL');
        }
    }
}

