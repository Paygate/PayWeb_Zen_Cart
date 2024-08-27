<?php
/**
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[0][] = array(
    'autoType' => 'require',
    'loadFile' => DIR_WS_INCLUDES . '/modules/pages/paygatepaywebv3_process/header_php.php'
);
