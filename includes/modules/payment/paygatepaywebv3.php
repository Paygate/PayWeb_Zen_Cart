<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

// ##################################################################################
// Title                     : PayGate South Africa ZenCart Payment Module
//                             Uses the PayWeb3 interface
// Version                   : 1.0.4
// Author                    : App Inlet (Pty) Ltd
// Last modification date    : 2022-05
// Notes                     : A payment module extenstion for ZenCart.
//                             You will require a PayGate account to make use of this
//                             module in a live environment.
//                             Visit www.paygate.co.za for more info.
// ##################################################################################

// phpcs:ignore
class PaygatePaywebV3 extends Paygate
{
    public $code, $title, $description, $enabled, $sort_order, $order_status, $form_action_url, $testmode, $useipn, $_check;

    // class constructor
    public function __construct()
    {
        global $order;

        $this->code        = 'paygatepaywebv3';
        $this->title       = MODULE_PAYMENT_PAYGATEPAYWEB3_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_PAYGATEPAYWEB3_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_PAYMENT_PAYGATEPAYWEB3_SORT_ORDER;
        $this->enabled     = ((MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS == 'True') ? true : false);

        $this->testmode = MODULE_PAYMENT_PAYGATEPAYWEB3_TESTMODE === 'True';
        $this->useipn   = MODULE_PAYMENT_PAYGATEPAYWEB3_USEIPN === 'True';

        if ((int)MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }

        $this->form_action_url = 'https://secure.paygate.co.za/payweb3/process.trans';
    }

    // phpcs:ignore
    public function update_status()
    {
        global $order, $db;

        if (($this->enabled) && ((int)MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE > 0)) {
            $check_flag = "";
            $check      = $db->Execute(
                "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id"
            );
            while ( ! $check->EOF) {
                if ($check->Fields['zone_id'] < 1 || ($check->Fields['zone_id'] == $order->billing['zone_id'])) {
                    $check_flag = true;
                    break;
                }
            }

            if ( ! $check_flag) {
                $this->enabled = false;
            }
        }
    }

    // phpcs:ignore
    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return array(
            'id'     => $this->code,
            'module' => $this->title
        );
    }

    // phpcs:ignore
    public function pre_confirmation_check()
    {
        return false;
    }

    public function confirmation()
    {
        return false;
    }

    /**
     * Initiate payment request to PayGate PayWeb
     * and then redirect to payment portal
     *
     * @return string
     */
    // phpcs:ignore
    public function process_button()
    {
        global $order, $db;

        $this->define_table();

        $pgPayGateID            = MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID;
        $_SESSION['PAYGATE_ID'] = $pgPayGateID;
        $pgReference            = $this->createUUID();
        $pgAmount               = (string)((int)($order->info['total'] * 100));
        $pgCurrency             = $order->info['currency'];
        $pgReturnURL            = zen_href_link('paygatepaywebv3_checkout_process');
        $pgTransactionDate      = gmstrftime("%Y-%m-%d %H:%M");
        $pgCustomerEmail        = $order->customer['email_address'];
        $pgNotifyURL            = $pgReturnURL;

        /***************************************************************/
        /* Concatenate the fields above to form the source of the checksum */
        /* Then append the encryption key                              */
        /***************************************************************/
        $fields = array(
            'PAYGATE_ID'       => $pgPayGateID,
            'REFERENCE'        => $pgReference,
            'AMOUNT'           => $pgAmount,
            'CURRENCY'         => $pgCurrency,
            'RETURN_URL'       => $pgReturnURL . '&uuid=' . $pgReference,
            'TRANSACTION_DATE' => $pgTransactionDate,
            'LOCALE'           => 'en-za',
            'COUNTRY'          => $order->customer['country']['iso_code_3'],
            'EMAIL'            => $pgCustomerEmail,
        );

        // Use notify not redirect in enabled
        if ($this->useipn) {
            $fields['NOTIFY_URL'] = $pgNotifyURL;
        }

        $fields['USER3'] = 'zencart-v1.0.4';

        $fields['CHECKSUM'] = md5(implode('', $fields) . MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY);

        $response = $this->curlPost('https://secure.paygate.co.za/payweb3/initiate.trans', $fields);
        parse_str($response, $fields);

        if (is_array($fields) && count($fields) === 4 && isset($fields['PAY_REQUEST_ID'])) {
            // Store the session data
            $customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;
            if ($customer_id) {
                if ( ! defined('TABLE_PAYGATEPAYWEBV3')) {
                    define('TABLE_PAYGATEPAYWEBV3', DB_PREFIX . 'paygatepaywebv3');
                }
                $sql = self::INSERT_STMT . TABLE_PAYGATEPAYWEBV3 . " (";
                $sql .= "customer_id, pay_request_id, session_data) values (";
                $sql .= ":customer_id, :pay_request_id, :session_data)";

                $sql = $db->bindVars($sql, ':customer_id', $customer_id, 'integer');
                $sql = $db->bindVars($sql, ':pay_request_id', $fields['PAY_REQUEST_ID'], 'string');
                $sql = $db->bindVars($sql, ':session_data', serialize($_SESSION), 'string');
                $db->Execute($sql);
            }

            // The process_button_string contains the form that is sent to PayGate.
            return zen_draw_hidden_field('PAY_REQUEST_ID', $fields['PAY_REQUEST_ID']) .
                   zen_draw_hidden_field('CHECKSUM', $fields['CHECKSUM']);
        }

        return '';
    }

    // phpcs:ignore
    public function getOrderStatus()
    {
        return $this->order_status;
    }

    // phpcs:ignore
    public function getUseipn()
    {
        return $this->useipn;
    }

    /**
     * Redirect from PayGate portal after payment
     * Handles notify response from portal as well
     *
     * Query and verify the transaction status before after_process is run
     */
    // phpcs:ignore
    public function before_process()
    {
        global $db, $order;
        $GLOBALS['IS_PAYGATE_NOTIFY'] = false;

        $this->define_table();

        $fields = [];

        $_POST = Paygate::filter_sanitize_post();

        if (isset($_POST['RESULT_CODE'])) {
            $fields = Paygate::validate_checksum();
        } else {
            // Follow up transaction
            $fields             = array(
                'PAYGATE_ID'     => MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID,
                'PAY_REQUEST_ID' => filter_var($_POST['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING),
                'REFERENCE'      => filter_var($_GET['uuid'], FILTER_SANITIZE_STRING),
            );
            $fields['CHECKSUM'] = md5(implode('', $fields) . MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY);
            $response           = $this->curlPost('https://secure.paygate.co.za/payweb3/query.trans', $fields);
            parse_str($response, $fields);
        }
        $GLOBALS['PAY_REQUEST_ID'] = $fields['PAY_REQUEST_ID'];

        if ($fields['ERROR']) {
            $GLOBALS['PAYGATE_ERROR'] = $fields['ERROR'];
            zen_redirect(
                zen_href_link('declined_transaction', 'status=10&error=' . $fields['ERROR'], 'SSL', true, false)
            );
        } elseif ($fields['TRANSACTION_STATUS']) {
            if ($fields['TRANSACTION_STATUS'] == 1) {
                $GLOBALS['PAYGATE_TRANSACTION_STATUS']      = $fields['TRANSACTION_STATUS'];
                $GLOBALS['PAYGATE_TRANSACTION_STATUS_DESC'] = 'Approved';
                $GLOBALS['PAYGATE_TRANSACTION_ID']          = $fields['TRANSACTION_ID'];
                $GLOBALS['PAYGATE_REFERENCE']               = $fields['REFERENCE'];
                $GLOBALS['PAYGATE_AMOUNT']                  = $fields['AMOUNT'];
                $GLOBALS['RESULT_DESC']                     = $fields['RESULT_DESC'];
                $GLOBALS['RESULT_CODE']                     = $fields['RESULT_CODE'];
                $GLOBALS['AUTH_CODE']                       = $fields['AUTH_CODE'];
                $GLOBALS['TRANSACTION_ID']                  = $fields['TRANSACTION_ID'];
                $GLOBALS['PAY_METHOD']                      = $fields['PAY_METHOD'];
                $GLOBALS['PAY_METHOD_DETAIL']               = $fields['PAY_METHOD_DETAIL'];
                $this->order_status                         = 2; // Processing

                // Empty cart
                include_once DIR_WS_CLASSES . 'shopping_cart.php';
                $cart = new shoppingCart();
                $cart->reset(true);
            } elseif ($fields['TRANSACTION_STATUS'] == 2) {
                zen_redirect(zen_href_link('declined_transaction', 'status=2', 'SSL', true, false));
            } elseif ($fields['TRANSACTION_STATUS'] == 4) {
                zen_redirect(
                    zen_href_link(
                        FILENAME_CHECKOUT_PAYMENT,
                        'error_message=' . urlencode('User cancelled transaction, ' . $fields['ERROR']),
                        'SSL',
                        true,
                        false
                    )
                );
            } else {
                zen_redirect(zen_href_link('declined_transaction', 'status=9', 'SSL', true, false));
            }
        }
    }

    // phpcs:ignore
    public function define_table()
    {
        if ( ! defined('TABLE_PAYGATEPAYWEBV3')) {
            define('TABLE_PAYGATEPAYWEBV3', DB_PREFIX . 'paygatepaywebv3');
        }
    }

    // phpcs:ignore
    public function after_process()
    {
        global $insert_id, $db, $order, $currencies;

        $this->define_table();

        $sql     = "select * from " . TABLE_PAYGATEPAYWEBV3 . " where pay_request_id = :id";
        $sql     = $db->bindVars($sql, ':id', $GLOBALS['PAY_REQUEST_ID'], 'string');
        $rec     = $db->Execute($sql);
        $records = [];
        foreach ($rec as $r) {
            $records[] = $r;
        }
        $record = $records[0];

        if ((int)$record['orders_id'] === 0 && (int)$insert_id != 0 && ((int)$record['orders_notified'] != 1)) {
            // This response has not yet been processed
            $subject = "PayGate processed zen-cart order, OrderID: " . $insert_id;
            $message = "Order has been " . $GLOBALS['PAYGATE_TRANSACTION_STATUS_DESC'] . "\n" .
                       "\n" .
                       "The order details are:\n" .
                       "Order Number: " . $insert_id . "\n" .
                       "PayGate Transaction Reference: " . $GLOBALS['PAYGATE_REFERENCE'] . "\n" .
                       "Processed Amount: " . number_format((int)$GLOBALS['PAYGATE_AMOUNT'] / 100, 2) . "\r\n";

            $result_code   = $GLOBALS['RESULT_CODE'];
            $orders_status = Paygate::validate_order_status($result_code);

            $sql    = self::UPDATE_STMT . " " . TABLE_PAYGATEPAYWEBV3;
            $sql    .= " set orders_id = :orders_id, ";
            $notify = $GLOBALS['IS_PAYGATE_NOTIFY'];
            $sql    .= Paygate::checkIsNotified($notify);
            $sql    .= "orders_status = :orders_status ";
            $sql    .= "where pay_request_id = :pay_request_id";
            $sql    = $db->bindVars($sql, ':orders_id', $insert_id, 'integer');
            $sql    = $db->bindVars($sql, ':orders_status', $orders_status, 'integer');
            $sql    = $db->bindVars($sql, ':pay_request_id', $GLOBALS['PAY_REQUEST_ID'], 'integer');
            $db->Execute($sql);

            $sql = self::UPDATE_STMT . " " . TABLE_ORDERS . " set orders_status = :orders_status where orders_id = :orders_id";
            $sql = $db->bindVars(
                $sql,
                ':orders_status',
                $orders_status,
                'integer'
            );
            $sql = $db->bindVars(
                $sql,
                ':orders_id',
                $insert_id,
                'integer'
            );
            $db->Execute($sql);

            $sql = self::INSERT_STMT . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values (:orderComments, :orderID, :orderStatus, -1, now() )";

            $orderComments = 'refrenceID: ' . $GLOBALS['PAYGATE_REFERENCE'] . ' RESULT_DESC: ' . $GLOBALS['RESULT_DESC'] . ' RESULT_CODE: ' . $GLOBALS['RESULT_CODE'] . ' AUTH_CODE: ' . $GLOBALS['AUTH_CODE'] . ' PAY_METHOD: ' . $GLOBALS['PAY_METHOD'] . ' PAY_METHOD_DETAIL: ' . $GLOBALS['PAY_METHOD_DETAIL'];

            $notify        = $GLOBALS['IS_PAYGATE_NOTIFY'];
            $orderComments = Paygate::validate_globals($notify, $orderComments);

            $sql = $db->bindVars(
                $sql,
                ':orderComments',
                $orderComments,
                'string'
            );
            $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
            $sql = $db->bindVars($sql, ':orderStatus', $orders_status, 'integer');
            $db->Execute($sql);

            zen_mail(
                '',
                MODULE_PAYMENT_PAYGATEPAYWEB3_AUTH_EMAIL,
                $subject,
                $message,
                STORE_OWNER,
                STORE_OWNER_EMAIL_ADDRESS
            );
        }

        return false;
    }

    // phpcs:ignore
    public function get_error()
    {
        return array(
            'title' => MODULE_PAYMENT_PAYGATEPAYWEB3_TEXT_ERROR,
            'error' => stripslashes(urldecode($_GET['error']))
        );
    }

    public function check()
    {
        global $db;
        if ( ! isset($this->_check)) {
            $check_query  = $db->Execute(
                "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS'"
            );
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    public function install()
    {
        global $db;

        $common_configuration_field = 'configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order';
        $insert_stmt                = self::INSERT_STMT . TABLE_CONFIGURATION;

        $queries = array(
            " ($common_configuration_field, set_function, date_added) values ('Enable PayGate PayWeb3 Module', 'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS', 'True', 'Do you want to accept PayGate payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())",
            " ($common_configuration_field, date_added) values ('PayGate ID', 'MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID', '10011072130', 'Your PayGate ID', '6', '0', now())",
            " ($common_configuration_field, date_added) values ('Encryption Key', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY', 'secret', 'Your Encryption Key; this must be identical to the Encryption Key on the BackOffice', '6', '0', now())",
            " ($common_configuration_field, set_function, date_added) values ('Enable Test Mode', 'MODULE_PAYMENT_PAYGATEPAYWEB3_TESTMODE', 'False', 'Do you want to enable test mode (sandbox)?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())",
            " ($common_configuration_field, set_function, date_added) values ('Enable IPN', 'MODULE_PAYMENT_PAYGATEPAYWEB3_USEIPN', 'True', 'Enables IPN, otherwise uses redirect order response', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())",
            " ($common_configuration_field, date_added) values ('Email Address', 'MODULE_PAYMENT_PAYGATEPAYWEB3_AUTH_EMAIL', '', 'Email address to send a warning email when transaction has chargeback risk', '6', '0', now())",
            " ($common_configuration_field, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())",
            " ($common_configuration_field, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())",
            " ($common_configuration_field, set_function, date_added) values ('Enable Debug Mode', 'MODULE_PAYMENT_PAYGATEPAYWEB3_DEBUG', 'False', 'Allows greater detailed error messages for testing purposes.', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())",
            " ($common_configuration_field, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYGATEPAYWEB3_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())"
        );

        foreach ($queries as $query) {
            $db->Execute(
                $insert_stmt . $query
            );
        }

        // Table for storing PayGate customer / session data
        if ( ! defined('TABLE_PAYGATEPAYWEBV3')) {
            define('TABLE_PAYGATEPAYWEBV3', DB_PREFIX . 'paygatepaywebv3');
        }
        $sql = "create table if not exists " . TABLE_PAYGATEPAYWEBV3 . "(";
        $sql .= "id int auto_increment primary key, ";
        $sql .= "customer_id int not null, ";
        $sql .= "pay_request_id varchar(50) not null, ";
        $sql .= "session_data text, ";
        $sql .= "orders_id int default 0, ";
        $sql .= "orders_notified int default 0, ";
        $sql .= "orders_status int default 0, ";
        $sql .= "date_added timestamp default CURRENT_TIMESTAMP)";
        $db->Execute($sql);
    }

    public function remove()
    {
        global $db;
        $db->Execute(
            "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode(
                "', '",
                $this->keys()
            ) . "')"
        );

        if ( ! defined('TABLE_PAYGATEPAYWEBV3')) {
            define('TABLE_PAYGATEPAYWEBV3', DB_PREFIX . 'paygatepaywebv3');
        }
        $db->Execute("drop table if exists " . TABLE_PAYGATEPAYWEBV3);
    }

    /**
     * pf_createUUID
     *
     * This function creates a pseudo-random UUID according to RFC 4122
     *
     * @see http://www.php.net/manual/en/function.uniqid.php#69164
     */
    public function createUUID()
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        return ($uuid);
    }

    public function keys()
    {
        return array(
            'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_TESTMODE',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_USEIPN',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_AUTH_EMAIL',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_DEBUG',
            'MODULE_PAYMENT_PAYGATEPAYWEB3_SORT_ORDER'
        );
    }
}

if ( ! class_exists(base)) {
    // phpcs:ignore
    class base
    {
        // Fallback if base class unknown
    }
}

// phpcs:ignore
class Paygate extends base
{
    const CUSTOMER_ID = ":customer_id";
    const INSERT_STMT = "insert into ";
    const UPDATE_STMT = "update";
    public $_check;

    public function check()
    {
        global $db;
        if ( ! isset($this->_check)) {
            $check_query  = $db->Execute(
                "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS'"
            );
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    // phpcs:ignore
    public function validate_checksum()
    {
        echo 'OK';
        $GLOBALS['IS_PAYGATE_NOTIFY'] = true;
        // Post back from notify endpoint
        $sentsum     = array_pop($_POST);
        $checkstring = '';
        foreach ($_POST as $item) {
            $checkstring .= $item;
        }
        $checkstring .= MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY;
        $calcsum     = md5($checkstring);
        if (hash_equals($sentsum, $calcsum)) {
            $fields = $_POST;
        } else {
            // Checksum doesn't agree
            die('Checksum error');
        }

        return $fields;
    }

    // phpcs:ignore
    public function filter_sanitize_post()
    {
        // Sanitise $_POST
        $post = [];
        foreach ($_POST as $key => $item) {
            $post[$key] = filter_var($item, FILTER_SANITIZE_STRING);
        }

        return $post;
    }

    // phpcs:ignore
    public function validate_globals($notify, $orderComments)
    {
        if ($notify) {
            $orderComments .= ' Processed by NOTIFY';
        } else {
            $orderComments .= ' Processed by REDIRECT';
        }

        return $orderComments;
    }

    // phpcs:ignore
    public function validate_order_status($result_code)
    {
        if ($result_code == '990017') {
            return 2;
        } else {
            return 1;
        }
    }

    public function checkIsNotified($notify)
    {
        if ($notify) {
            return "orders_notified = 1, ";
        }
    }

    public function curlPost($url, $fields)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, count($fields));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}
