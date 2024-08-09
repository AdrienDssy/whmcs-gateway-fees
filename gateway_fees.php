<?php

require_once __DIR__ . '/common_functions.php';
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) die("This file cannot be accessed directly");

function gateway_fees_config()
{
    $configarray = array(
        "name" => "Gatewayfees",
        "description" => "Gateway fees",
        "version" => "1.0",
        "author" => "AdKyNet SAS",
        "fields" => array(
            "enable_logs" => array(
                "FriendlyName" => "Enable Logs",
                "Type" => "yesno",
                "Description" => "Enable or disable logging.",
                "Default" => "No"
            ),
            "delete_table_on_deactivation" => array(
                "FriendlyName" => "Delete Table on Deactivation",
                "Type" => "yesno",
                "Description" => "Choose to delete the module's SQL table when the module is deactivated.",
                "Default" => "No"
            ),
        )
    );

    $gateways = Capsule::table('tblpaymentgateways')
        ->groupBy('gateway')
        ->pluck('gateway');

    $currencies = Capsule::table('tblcurrencies')->pluck('code');

    foreach ($gateways as $gateway) {
        foreach ($currencies as $currency) {
            $configarray['fields']["fee_1_" . $gateway . "_" . $currency] = array(
                "FriendlyName" => $gateway . ' ' . $currency,
                "Type" => "text",
                "Default" => "0.00",
                "Description" => "$"
            );
            $configarray['fields']["fee_2_" . $gateway . "_" . $currency] = array(
                "FriendlyName" => $gateway . ' ' . $currency,
                "Type" => "text",
                "Default" => "0.00",
                "Description" => "%"
            );
        }
    }

    return $configarray;
}

function gateway_fees_activate()
{
    $gateways = Capsule::table('tblpaymentgateways')
        ->groupBy('gateway')
        ->pluck('gateway');

    $currencies = Capsule::table('tblcurrencies')->pluck('code');

    foreach ($gateways as $gateway) {
        foreach ($currencies as $currency) {
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'gateway_fees', 'setting' => 'fee_1_' . $gateway . '_' . $currency],
                ['value' => '0.00']
            );
            log_to_file("DB Insert or Update: Module 'gateway_fees', Setting 'fee_1_" . $gateway . "_" . $currency . "', Value '0.00'");

            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'gateway_fees', 'setting' => 'fee_2_' . $gateway . '_' . $currency],
                ['value' => '0.00']
            );
            log_to_file("DB Insert or Update: Module 'gateway_fees', Setting 'fee_2_" . $gateway . "_" . $currency . "', Value '0.00'");
        }
    }
}

function gateway_fees_deactivate()
{
    $deleteTableOnDeactivation = Capsule::table('tbladdonmodules')
        ->where('module', 'gateway_fees')
        ->where('setting', 'delete_table_on_deactivation')
        ->value('value') === 'on';

    if ($deleteTableOnDeactivation) {
        Capsule::schema()->dropIfExists('tblgatewayfees');
        log_to_file("Table 'tblgatewayfees' deleted on module deactivation.");
    }
}
