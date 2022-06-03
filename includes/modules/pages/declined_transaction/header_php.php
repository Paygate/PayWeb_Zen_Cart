<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

$error  = empty($_GET['error']) ? '' : filter_var($_GET['error'], FILTER_SANITIZE_STRING);
$status = empty($_GET['status']) ? '' : filter_var($_GET['status'], FILTER_SANITIZE_NUMBER_INT);
if ($status == '2') {
    $breadcrumb->add('<Strong>Your transaction has been declined</Strong>');
} elseif ($status == '10') {
    $breadcrumb->add('<Strong>Your transaction has been declined, error: ' . $error . '</Strong>');
} else {
    $breadcrumb->add('<Strong>Your transaction has been declined, unknown error</Strong>');
}
