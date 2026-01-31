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

    // Fixed fees
    $fee1 = isset($params['fee_1_' . $paymentMethod . '_' . $currencyCode]) ? (float)$params['fee_1_' . $paymentMethod . '_' . $currencyCode] : 0;
    // Percentage fees
    $fee2 = isset($params['fee_2_' . $paymentMethod . '_' . $currencyCode]) ? (float)$params['fee_2_' . $paymentMethod . '_' . $currencyCode] : 0;

    $service_baseprice = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->sum('amount');
    $serviceID = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->value('relid');

    $totalFee = $fee1 + ($service_baseprice * $fee2 / 100);
    $taxConfiguration = Capsule::table('tblconfiguration')->where('setting', 'TaxType')->first();
    $isTaxInclusive = $taxConfiguration && $taxConfiguration->value === 'Inclusive';

    // Shows only the gateway fees when needed in the invoice
    if ($fee1 > 0.00 || $fee2 > 0.00) {
        Capsule::table('tblinvoiceitems')->insertOrUpdate([
            'invoiceid' => $invoiceId,
            'type' => 'Item',
            'description' => "Gateway Fee ({$fee2}% / {$fee1}{$currencysuffix})",
            'amount' => $totalFee,
            'taxed' => $taxGatewayFees ? 1 : 0, // Edit to 0 if you don't want to make the amount excluding tax
            'relid' => $serviceID,
            'notes' => 'gateway_fees'
        ]);
        log_to_file("DB Insert: tblinvoiceitems with totalFee=" . $totalFee);
    }

    // Re-calculate totals based on all items currently in the invoice
    $newTotalItems = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->sum('amount');
    $tax_rate = $invoice->taxrate;
    $newTax = 0;
    $newSubtotal = 0;
    $newTotal = 0;

    if ($isTaxInclusive) {
        $newTotal = $newTotalItems;
        $newTax = 0;
        
        if ($tax_rate > 0) {
            $taxedItemsSum = Capsule::table('tblinvoiceitems')
                ->where('invoiceid', $invoiceId)
                ->where('taxed', 1)
                ->sum('amount');

            $taxFactor = 1 + ($tax_rate / 100);
            $newTax = $taxedItemsSum - ($taxedItemsSum / $taxFactor);
        }
        
        $newSubtotal = $newTotal - $newTax;
    } else {
        // In Exclusive mode, the sum of item amounts is the Subtotal
        $newSubtotal = $newTotalItems;
        
        if ($tax_rate > 0) {
            $taxedItemsSubtotal = Capsule::table('tblinvoiceitems')
                ->where('invoiceid', $invoiceId)
                ->where('taxed', 1)
                ->sum('amount');
            
            $newTax = $taxedItemsSubtotal * ($tax_rate / 100);
        }
        $newTotal = $newSubtotal + $newTax;
    }

    // Check if an update is actually needed to avoid redundant DB writes
    $updateRequired = abs($invoice->subtotal - $newSubtotal) > 0.005 || 
                      abs($invoice->tax - $newTax) > 0.005 || 
                      abs($invoice->total - $newTotal) > 0.005;

    if ($updateRequired) {
        Capsule::table('tblinvoices')->where('id', $invoiceId)->update([
            'subtotal' => $newSubtotal,
            'tax' => $newTax,
            'total' => $newTotal
        ]);
        log_to_file("DB Update: tblinvoices updated. Subtotal: $newSubtotal, Tax: $newTax, Total: $newTotal");
    } else {
        log_to_file("DB Update Skipped: Totals match existing invoice.");
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
