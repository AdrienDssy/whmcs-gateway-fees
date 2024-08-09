<?php

use WHMCS\Database\Capsule;

function log_to_file($message)
{
    $enable_logs = Capsule::table('tbladdonmodules')
        ->where('module', 'gateway_fees')
        ->where('setting', 'enable_logs')
        ->value('value') === 'on';

    if ($enable_logs) {
        $logfile = dirname(__FILE__) . '/gateway_fees.log';
        $datetime = date('Y-m-d H:i:s');
        $formatted_message = "[$datetime] $message\n";
        file_put_contents($logfile, $formatted_message, FILE_APPEND);
    }
}
