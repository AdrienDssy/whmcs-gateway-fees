<?php

use WHMCS\Database\Capsule;

/**
 *   Created By AdKyNet SAS - Adrien Dessey (Copyright AdKyNet SAS)
 *   Host : https://www.adkynet.com/en
 */

if (!defined("WHMCS")) die("This file cannot be accessed directly");

function gateway_fees_config()
{
    $configarray = array(
        "name" => "Gatewayfees",
        "description" => "Gateway fees",
        "version" => "1.0",
        "author" => "AdKyNet SAS"
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

    $currencies = Capsule::table('tblcurrencies')->limit(2)->pluck('code');

    foreach ($gateways as $gateway) {
        foreach ($currencies as $currency) {
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'gateway_fees', 'setting' => 'fee_1_' . $gateway . '_' . $currency],
                ['value' => '0.00']
            );
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'gateway_fees', 'setting' => 'fee_2_' . $gateway . '_' . $currency],
                ['value' => '0.00']
            );
        }
    }
}
