<?php

use WHMCS\Database\Capsule;

/**
 *   Created By AdKyNet SAS - Adrien Dessey (Copyright AdKyNet SAS)
 *   Host : https://www.adkynet.com/en
 */

function update_gateway_fee3($vars)
{
    $id = (int)$vars['invoiceid'];
    updateInvoiceTotal($id);
}

function update_gateway_fee1($vars)
{
    $id = (int)$vars['invoiceid'];
    $invoice = Capsule::table('tblinvoices')->where('id', $id)->first();
    
    if ($invoice) {
        update_gateway_fee2(array(
            'paymentmethod' => $invoice->paymentmethod,
            'invoiceid' => $invoice->id
        ));
    }
}

function update_gateway_fee2($vars)
{
    $invoiceId = (int)$vars['invoiceid'];
    $paymentMethod = $vars['paymentmethod'];

    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    $currency = Capsule::table('tblclients')
        ->where('id', $invoice)
        ->value('currency');

    $currencyCode = Capsule::table('tblcurrencies')
        ->where('id', $currency)
        ->value('code');

    Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where('notes', 'gateway_fees')
        ->delete();

    $params = Capsule::table('tbladdonmodules')
        ->whereIn('setting', [
            'fee_2_' . $paymentMethod . '_' . $currencyCode,
            'fee_1_' . $paymentMethod . '_' . $currencyCode
        ])
        ->pluck('value', 'setting')
        ->toArray();

    $fee1 = isset($params['fee_1_' . $paymentMethod . '_' . $currencyCode]) ? (float)$params['fee_1_' . $paymentMethod . '_' . $currencyCode] : 0;
    $fee2 = isset($params['fee_2_' . $paymentMethod . '_' . $currencyCode]) ? (float)$params['fee_2_' . $paymentMethod . '_' . $currencyCode] : 0;

    $total = InvoiceTotal($invoiceId);
    $amountdue = 0;
    $description = '';

    if ($total > 0) {
        $amountdue = $fee1 + $total * $fee2 / 100;

        if ($fee1 > 0 && $fee2 > 0) {
            $description = $fee1 . '+' . $fee2 . '%';
        } elseif ($fee2 > 0) {
            $description = $fee2 . '%';
        } elseif ($fee1 > 0) {
            $description = $fee1;
        }
    }

    if ($description) {
        Capsule::table('tblinvoiceitems')->insert(array(
            "userid" => $_SESSION['uid'],
            "invoiceid" => $invoiceId,
            "type" => "Fee",
            "notes" => "gateway_fees",
            "description" => getGatewayName2($paymentMethod) . " Gateway Fee ($description) " . $currencyCode,
            "amount" => $amountdue,
            "taxed" => "0",
            "duedate" => Capsule::raw("NOW()"),
            "paymentmethod" => $paymentMethod
        ));
    }

    updateInvoiceTotal($invoiceId);
}

function InvoiceTotal($id)
{
    global $CONFIG;

    $nontaxsubtotal = 0;
    $taxsubtotal = 0;

    $items = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $id)
        ->get();

    foreach ($items as $item) {
        if ($item->taxed == "1") {
            $taxsubtotal += $item->amount;
        } else {
            $nontaxsubtotal += $item->amount;
        }
    }

    $subtotal = $nontaxsubtotal + $taxsubtotal;

    $invoice = Capsule::table('tblinvoices')
        ->where('id', $id)
        ->first();

    $userid = $invoice->userid;
    $credit = $invoice->credit;
    $taxrate = $invoice->taxrate;
    $taxrate2 = $invoice->taxrate2;

    if (!function_exists("getClientsDetails")) {
        require_once dirname(__FILE__) . "/clientfunctions.php";
    }

    $clientsdetails = getClientsDetails($userid);
    $tax = 0;
    $tax2 = 0;

    if ($CONFIG['TaxEnabled'] == "on" && !$clientsdetails['taxexempt']) {
        if ($taxrate != "0.00") {
            if ($CONFIG['TaxType'] == "Inclusive") {
                $taxrate = $taxrate / 100 + 1;
                $calc1 = $taxsubtotal / $taxrate;
                $tax = $taxsubtotal - $calc1;
            } else {
                $taxrate = $taxrate / 100;
                $tax = $taxsubtotal * $taxrate;
            }
        }

        if ($taxrate2 != "0.00") {
            if ($CONFIG['TaxL2Compound']) {
                $taxsubtotal += $tax;
            }

            if ($CONFIG['TaxType'] == "Inclusive") {
                $taxrate2 = $taxrate2 / 100 + 1;
                $calc1 = $taxsubtotal / $taxrate2;
                $tax2 = $taxsubtotal - $calc1;
            } else {
                $taxrate2 = $taxrate2 / 100;
                $tax2 = $taxsubtotal * $taxrate2;
            }
        }

        $tax = round($tax, 2);
        $tax2 = round($tax2, 2);
    }

    if ($CONFIG['TaxType'] == "Inclusive") {
        $subtotal = $subtotal - $tax - $tax2;
    } else {
        $total = $subtotal + $tax + $tax2;
    }

    if ($credit > 0) {
        if ($total < $credit) {
            $total = 0;
            $remainingcredit = $total - $credit;
        } else {
            $total -= $credit;
        }
    }

    return format_as_currency($total);
}

function getGatewayName2($modulename)
{
    return Capsule::table('tblpaymentgateways')
        ->where('gateway', $modulename)
        ->where('setting', 'name')
        ->value('value');
}

add_hook("InvoiceChangeGateway", 1, "update_gateway_fee2");
add_hook("InvoiceCreated", 1, "update_gateway_fee1");
add_hook("AdminInvoicesControlsOutput", 2, "update_gateway_fee3");
add_hook("AdminInvoicesControlsOutput", 1, "update_gateway_fee1");
add_hook("InvoiceCreationAdminArea", 1, "update_gateway_fee1");
add_hook("InvoiceCreationAdminArea", 2, "update_gateway_fee3");
