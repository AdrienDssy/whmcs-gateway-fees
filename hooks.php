<?php

require_once __DIR__ . '/common_functions.php';
use WHMCS\Database\Capsule;

function update_gateway_fee1($vars)
{
    log_to_file("update_gateway_fee1 called with invoiceid: " . $vars['invoiceid']);
    $id = (int)$vars['invoiceid'];
    $invoice = Capsule::table('tblinvoices')->where('id', $id)->first();

    if ($invoice) {
        log_to_file("Invoice found: " . print_r($invoice, true));
        update_gateway_fee2(array(
            'paymentmethod' => $invoice->paymentmethod,
            'invoiceid' => $invoice->id
        ));
    }
}

function update_gateway_fee2($vars)
{
    log_to_file("update_gateway_fee2 called with invoiceid: " . $vars['invoiceid'] . " and paymentmethod: " . $vars['paymentmethod']);
    $invoiceId = (int)$vars['invoiceid'];
    $paymentMethod = $vars['paymentmethod'];

    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice) {
        log_to_file("Invoice not found for id: " . $invoiceId);
        return;
    }

    $currency = Capsule::table('tblclients')->where('id', $invoice->userid)->value('currency');
    $taxGatewayFees = Capsule::table('tbladdonmodules')->where('module', 'gateway_fees')->where('setting', 'tax_gateway_fees')->value('value');
    $currencyCode = Capsule::table('tblcurrencies')->where('id', $currency)->value('code');
    $currencysuffix = Capsule::table('tblcurrencies')->where('id', $currency)->value('suffix');

    Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where('notes', 'gateway_fees')
        ->delete();
    log_to_file("DB Delete: tblinvoiceitems where invoiceid=" . $invoiceId . " and notes='gateway_fees'");

    $params = Capsule::table('tbladdonmodules')
        ->whereIn('setting', [
            'fee_2_' . $paymentMethod . '_' . $currencyCode,
            'fee_1_' . $paymentMethod . '_' . $currencyCode
        ])
        ->pluck('value', 'setting')
        ->toArray();

    $fee1 = isset($params['fee_1_' . $paymentMethod . '_' . $currencyCode]) ? (float)$params['fee_1_' . $paymentMethod . '_' . $currencyCode] : 0;
    $fee2 = isset($params['fee_2_' . $paymentMethod . '_' . $currencyCode]) ? (float)$params['fee_2_' . $paymentMethod . '_' . $currencyCode] : 0;
    $service_baseprice = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->value('amount');
    
    $totalFee = $fee1 + ($service_baseprice * $fee2 / 100);

    // Shows only the gateway fees when needed in the invoice
    if($fee1 > 0.00 || $fee2 > 0.00) {
        Capsule::table('tblinvoiceitems')->insert([
            'invoiceid' => $invoiceId,
            'type' => 'Item',
            'description' => "Gateway Fee ({$fee2}% / {$fee1}{$currencysuffix})",
            'amount' => $totalFee,
            'taxed' => $taxGatewayFees?1:0, // Edit to 0 if you don't want to make the amount excluding tax
            'notes' => 'gateway_fees'
        ]);
        log_to_file("DB Insert: tblinvoiceitems with totalFee=" . $totalFee);
    }

    updateInvoiceTotal($vars['invoiceid']);
}

function update_gateway_fee3($vars)
{
    log_to_file("update_gateway_fee3 called");
    // Example of treatment if necessary
}

add_hook("InvoiceChangeGateway", 1, "update_gateway_fee2");
add_hook("InvoiceCreated", 1, "update_gateway_fee1");
add_hook("AdminInvoicesControlsOutput", 2, "update_gateway_fee3");
add_hook("AdminInvoicesControlsOutput", 1, "update_gateway_fee1");
add_hook("InvoiceCreationAdminArea", 1, "update_gateway_fee1");
add_hook("InvoiceCreationAdminArea", 2, "update_gateway_fee3");
