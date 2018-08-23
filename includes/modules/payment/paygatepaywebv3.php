<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */

// ##################################################################################
// Title                     : PayGate South Africa ZenCart Payment Module
//                             Uses the PayWeb3 interface
// Version                   : 1.0.3
// Author                    : App Inlet (Pty) Ltd
// Last modification date    : 2018-06-28
// Notes                     : A payment module extenstion for ZenCart.
//                             You will require a PayGate account to make use of this
//                             module in a live environment.
//                             Visit www.paygate.co.za for more info.
// ##################################################################################

class paygatepaywebv3
{
    public $code, $title, $description, $enabled;

// class constructor
    public function paygatepaywebv3()
    {
        global $order;

        $this->code        = 'paygatepaywebv3';
        $this->title       = MODULE_PAYMENT_PAYGATEPAYWEB3_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_PAYGATEPAYWEB3_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_PAYMENT_PAYGATEPAYWEB3_SORT_ORDER;
        $this->enabled     = (  ( MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS == 'True' ) ? true : false );

        if ( (int) MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID > 0 ) {
            $this->order_status = MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID;
        }

        if ( is_object( $order ) ) {
            $this->update_status();
        }

        $this->form_action_url = 'https://secure.paygate.co.za/payweb3/process.trans';
    }

    public function update_status()
    {
        global $order, $db;

        if (  ( $this->enabled == true ) && ( (int) MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE > 0 ) ) {
            $check_flag = false;
            $check      = $db->Execute( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id" );
            while ( !$check->EOF ) {
                if ( $check->Fields['zone_id'] < 1 ) {
                    $check_flag = true;
                    break;
                } elseif ( $check->Fields['zone_id'] == $order->billing['zone_id'] ) {
                    $check_flag = true;
                    break;
                }
            }

            if ( $check_flag == false ) {
                $this->enabled = false;
            }
        }
    }

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return array( 'id' => $this->code,
            'module'          => $this->title );
    }

    public function pre_confirmation_check()
    {
        return false;
    }

    public function confirmation()
    {
        return false;
    }

    public function process_button()
    {
        global $HTTP_POST_VARS, $shipping_cost, $shipping_selected, $shipping_method, $customer_id, $order;

        $pgPayGateID       = MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID;
        $pgReference       = $this->createUUID();
        $pgAmount          = (string) ( (int) ( $order->info['total'] * 100 ) );
        $pgCurrency        = $order->info['currency'];
        $pgReturnURL       = zen_href_link( FILENAME_CHECKOUT_PROCESS, '', 'SSL' );
        $pgTransactionDate = gmstrftime( "%Y-%m-%d %H:%M" );
        $pgCustomerEmail   = $order->customer['email_address'];
        $pgNotifyURL       = zen_href_link( 'notifyurl.php' );

        /***************************************************************/
        /* Concate the fields above to form the source of the checksum */
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
            'NOTIFY_URL'       => $pgReturnURL,
        );
        $fields['CHECKSUM'] = md5( implode( '', $fields ) . MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY );
        $response           = $this->curlPost( 'https://secure.paygate.co.za/payweb3/initiate.trans', $fields );
        parse_str( $response, $fields );

        // The process_button_string contains the form that is sent to PayGate.
        $process_button_string = zen_draw_hidden_field( 'PAY_REQUEST_ID', $fields['PAY_REQUEST_ID'] ) .
        zen_draw_hidden_field( 'CHECKSUM', $fields['CHECKSUM'] );

        return $process_button_string;
    }

    public function before_process()
    {
        global $HTTP_POST_VARS, $order;

        // Follow up transaction
        $fields = array(
            'PAYGATE_ID'     => MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID,
            'PAY_REQUEST_ID' => filter_var( $_POST['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING ),
            'REFERENCE'      => filter_var( $_GET['uuid'], FILTER_SANITIZE_STRING ),
        );
        $fields['CHECKSUM'] = md5( implode( '', $fields ) . MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY );
        $response           = $this->curlPost( 'https://secure.paygate.co.za/payweb3/query.trans', $fields );
        parse_str( $response, $fields );
        if ( $fields['ERROR'] ) {
            $GLOBALS['PAYGATE_ERROR'] = $fields['ERROR'];
            zen_redirect( zen_href_link( 'declined_transaction', 'status=10&error=' . $fields['ERROR'], 'SSL', true, false ) );
        } else if ( $fields['TRANSACTION_STATUS'] ) {
            if ( $fields['TRANSACTION_STATUS'] == 1 ) {
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
                $this->order_status                         = 1;
            } else if ( $fields['TRANSACTION_STATUS'] == 2 ) {
                zen_redirect( zen_href_link( 'declined_transaction', 'status=2', 'SSL', true, false ) );
            } else if ( $fields['TRANSACTION_STATUS'] == 4 ) {
                zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( 'User cancelled transaction, ' . $fields['ERROR'] ), 'SSL', true, false ) );
            } else {
                zen_redirect( zen_href_link( 'declined_transaction', 'status=9', 'SSL', true, false ) );
            }
        }
    }

    public function after_process()
    {
        global $insert_id, $db, $order, $currencies;
        $subject = "<br>PayGate processed zen-cart order, OrderID: " . $insert_id;
        $message = "Order has been " . $GLOBALS['PAYGATE_TRANSACTION_STATUS_DESC'] . "\n" .
        "\n" .
        "The order details are:\n" .
        "Order Number: " . $insert_id . "\n" .
        "PayGate Transaction Reference: " . $GLOBALS['PAYGATE_REFERENCE'] . "\n" .
        "Processed Amount: " . number_format( (int) $GLOBALS['PAYGATE_AMOUNT'] / 100, 2 ) . "\r\n";
        zen_mail( '', MODULE_PAYMENT_PAYGATEPAYWEB3_AUTH_EMAIL, $subject, $message, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );

        $sql              = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values (:orderComments, :orderID, :orderStatus, -1, now() )";
        $currency_comment = '';

        $sql = $db->bindVars( $sql, ':orderComments', 'refrenceID: ' . $GLOBALS['PAYGATE_REFERENCE'] . ' RESULT_DESC: ' . $GLOBALS['RESULT_DESC'] . ' RESULT_CODE: ' . $GLOBALS['RESULT_CODE'] . ' AUTH_CODE: ' . $GLOBALS['AUTH_CODE'] . ' PAY_METHOD: ' . $GLOBALS['PAY_METHOD'] . ' PAY_METHOD_DETAIL: ' . $GLOBALS['PAY_METHOD_DETAIL'], 'string' );
        $sql = $db->bindVars( $sql, ':orderID', $insert_id, 'integer' );
        $sql = $db->bindVars( $sql, ':orderStatus', $this->order_status, 'integer' );
        $db->Execute( $sql );

        return false;
    }

    public function get_error()
    {
        global $HTTP_GET_VARS;

        $error = array( 'title' => MODULE_PAYMENT_PAYGATEPAYWEB3_TEXT_ERROR,
            'error'                => stripslashes( urldecode( $HTTP_GET_VARS['error'] ) ) );

        return $error;
    }

    public function check()
    {
        global $db;
        if ( !isset( $this->_check ) ) {
            $check_query  = $db->Execute( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS'" );
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    public function install()
    {
        global $db;
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable PayGate PayWeb3 Module', 'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS', 'True', 'Do you want to accept PayGate payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('PayGate ID', 'MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID', '10011072130', 'Your PayGate ID', '6', '0', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Encryption Key', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY', 'secret', 'Your Encryption Key; this must be identical to the Encryption Key on the BackOffice', '6', '0', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Email Address', 'MODULE_PAYMENT_PAYGATEPAYWEB3_AUTH_EMAIL', '', 'Email address to send a warning email when transaction has chargeback risk', '6', '0', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Debug Mode', 'MODULE_PAYMENT_PAYGATEPAYWEB3_DEBUG', 'False', 'Allows greater detailed error messages for testing purposes.', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())" );
        $db->Execute( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYGATEPAYWEB3_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())" );
    }

    public function remove()
    {
        global $db;
        $db->Execute( "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );
    }

    public function keys()
    {
        return array( 'MODULE_PAYMENT_PAYGATEPAYWEB3_STATUS', 'MODULE_PAYMENT_PAYGATEPAYWEB3_PAYGATEID', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ENCRYPTIONKEY', 'MODULE_PAYMENT_PAYGATEPAYWEB3_AUTH_EMAIL', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ORDER_STATUS_ID', 'MODULE_PAYMENT_PAYGATEPAYWEB3_ZONE', 'MODULE_PAYMENT_PAYGATEPAYWEB3_DEBUG', 'MODULE_PAYMENT_PAYGATEPAYWEB3_SORT_ORDER' );
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
        $uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );

        return ( $uuid );
    }

    public function curlPost( $url, $fields )
    {
        $curl = curl_init( $url );
        curl_setopt( $curl, CURLOPT_POST, count( $fields ) );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $fields );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec( $curl );
        curl_close( $curl );
        return $response;
    }
}
