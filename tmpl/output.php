<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/** @var PlgPciZugferdBuilder $builder */
$builder = $displayData['builder'];
$orderData = $builder->getOrderData();
$params = $builder->getParams();

$common = $orderData['common'];
$bas = $orderData['bas'] ?? [];
$products = $orderData['products'] ?? [];
$total = $orderData['total'] ?? [];
$taxrecapitulation = $orderData['taxrecapitulation'] ?? [];
$namespaces = $builder->getNamespaces();

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

$root = $dom->createElementNS($namespaces['rsm'], 'rsm:CrossIndustryInvoice');
foreach ($namespaces as $prefix => $uri) {
    if ($prefix !== 'rsm') {
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $uri);
    }
}
$dom->appendChild($root);

// ExchangedDocumentContext
$context = $dom->createElementNS($namespaces['rsm'], 'rsm:ExchangedDocumentContext');
$root->appendChild($context);

$profile = $params->get('profile', 'EN16931');
$profileUri = $builder->getProfileUri($profile);

$guidelineParam = $dom->createElementNS($namespaces['ram'], 'ram:GuidelineSpecifiedDocumentContextParameter');
$context->appendChild($guidelineParam);

$id = $dom->createElementNS($namespaces['ram'], 'ram:ID', $profileUri);
$guidelineParam->appendChild($id);


// ExchangedDocument
$doc = $dom->createElementNS($namespaces['rsm'], 'rsm:ExchangedDocument');
$root->appendChild($doc);

$id = $dom->createElementNS($namespaces['ram'], 'ram:ID', $common->invoice_number ?? '');
$doc->appendChild($id);

$typeCode = $dom->createElementNS($namespaces['ram'], 'ram:TypeCode', '380');
$doc->appendChild($typeCode);

$issueDateTime = $dom->createElementNS($namespaces['ram'], 'ram:IssueDateTime');
$doc->appendChild($issueDateTime);

$dateString = $dom->createElementNS($namespaces['udt'], 'udt:DateTimeString', date('Ymd', strtotime($common->invoice_date ?? $common->date)));
$dateString->setAttribute('format', '102');
$issueDateTime->appendChild($dateString);


// SupplyChainTradeTransaction
$transaction = $dom->createElementNS($namespaces['rsm'], 'rsm:SupplyChainTradeTransaction');
$root->appendChild($transaction);

// LineItems
$lineNumber = 1;
foreach ($products as $product) {
    $netto = $builder->getAmount($product->netto, $product->netto_currency ?? 0);
    $tax = $builder->getAmount($product->tax, $product->tax_currency ?? 0);

    $lineItem = $dom->createElementNS($namespaces['ram'], 'ram:IncludedSupplyChainTradeLineItem');
    $transaction->appendChild($lineItem);

    $lineDoc = $dom->createElementNS($namespaces['ram'], 'ram:AssociatedDocumentLineDocument');
    $lineItem->appendChild($lineDoc);
    $lineId = $dom->createElementNS($namespaces['ram'], 'ram:LineID', (string)$lineNumber);
    $lineDoc->appendChild($lineId);

    $tradeProduct = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTradeProduct');
    $lineItem->appendChild($tradeProduct);
    if (!empty($product->sku)) {
        $sellerAssignedId = $dom->createElementNS($namespaces['ram'], 'ram:SellerAssignedID', $product->sku);
        $tradeProduct->appendChild($sellerAssignedId);
    }
    $productName = $dom->createElementNS($namespaces['ram'], 'ram:Name', $product->title ?? '');
    $tradeProduct->appendChild($productName);

    $lineTradeAgreement = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedLineTradeAgreement');
    $lineItem->appendChild($lineTradeAgreement);

    $netPrice = $dom->createElementNS($namespaces['ram'], 'ram:NetPriceProductTradePrice');
    $lineTradeAgreement->appendChild($netPrice);
    $chargeAmount = $dom->createElementNS($namespaces['ram'], 'ram:ChargeAmount', number_format($netto, 2, '.', ''));
    $netPrice->appendChild($chargeAmount);

    $lineTradeDelivery = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedLineTradeDelivery');
    $lineItem->appendChild($lineTradeDelivery);
    $billedQuantity = $dom->createElementNS($namespaces['ram'], 'ram:BilledQuantity', (string)$product->quantity);
    $billedQuantity->setAttribute('unitCode', 'C62');
    $lineTradeDelivery->appendChild($billedQuantity);

    $lineTradeSettlement = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedLineTradeSettlement');
    $lineItem->appendChild($lineTradeSettlement);

    $taxRate = $product->default_tax_rate;
    /*$taxRate = 0;
    if ($netto > 0) {
        $taxRate = round(($tax / $netto) * 100, 2);
    }*/
    $applicableTradeTax = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableTradeTax');
    $lineTradeSettlement->appendChild($applicableTradeTax);
    $typeCode = $dom->createElementNS($namespaces['ram'], 'ram:TypeCode', 'VAT');
    $applicableTradeTax->appendChild($typeCode);
    $categoryCode = $dom->createElementNS($namespaces['ram'], 'ram:CategoryCode', 'S');
    $applicableTradeTax->appendChild($categoryCode);
    $ratePercent = $dom->createElementNS($namespaces['ram'], 'ram:RateApplicablePercent', number_format($taxRate, 2, '.', ''));
    $applicableTradeTax->appendChild($ratePercent);

    $monetarySummation = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTradeSettlementLineMonetarySummation');
    $lineTradeSettlement->appendChild($monetarySummation);
    $lineTotalAmount = $dom->createElementNS($namespaces['ram'], 'ram:LineTotalAmount', number_format($product->quantity * $netto, 2, '.', ''));
    $monetarySummation->appendChild($lineTotalAmount);

    $lineNumber++;
}


// ApplicableHeaderTradeAgreement
$agreement = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableHeaderTradeAgreement');
$transaction->appendChild($agreement);

$sellerParty = $dom->createElementNS($namespaces['ram'], 'ram:SellerTradeParty');
$agreement->appendChild($sellerParty);
$sellerName = $dom->createElementNS($namespaces['ram'], 'ram:Name', $params->get('seller_name', ''));
$sellerParty->appendChild($sellerName);

$sellerAddress = $dom->createElementNS($namespaces['ram'], 'ram:PostalTradeAddress');
$sellerParty->appendChild($sellerAddress);

// Seller Address Fields
$sellerAddressFields = [
    'PostcodeCode' => $params->get('seller_zip', ''),
    'LineOne' => $params->get('seller_street', ''),
    'CityName' => $params->get('seller_city', ''),
    'CountryID' => $params->get('seller_country', 'DE'),
];
foreach ($sellerAddressFields as $tag => $val) {
    if (!empty($val)) {
        $el = $dom->createElementNS($namespaces['ram'], 'ram:' . $tag, $val);
        $sellerAddress->appendChild($el);
    }
}


if ($params->get('seller_vat', '')) {
    $sellerTaxReg = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTaxRegistration');
    $sellerParty->appendChild($sellerTaxReg);
    $vatId = $dom->createElementNS($namespaces['ram'], 'ram:ID', $params->get('seller_vat', ''));
    $vatId->setAttribute('schemeID', 'VA');
    $sellerTaxReg->appendChild($vatId);
}

$billing = $bas['b'] ?? [];
$buyerParty = $dom->createElementNS($namespaces['ram'], 'ram:BuyerTradeParty');
$agreement->appendChild($buyerParty);

$buyerNameValue = trim(($billing['name_first'] ?? '') . ' ' . ($billing['name_last'] ?? ''));
if (!empty($billing['company'])) {
    $buyerNameValue = $billing['company'];
}
$buyerName = $dom->createElementNS($namespaces['ram'], 'ram:Name', $buyerNameValue);
$buyerParty->appendChild($buyerName);

$buyerAddress = $dom->createElementNS($namespaces['ram'], 'ram:PostalTradeAddress');
$buyerParty->appendChild($buyerAddress);

// Buyer Address Fields
$buyerAddressFields = [
    'PostcodeCode' => $billing['zip'] ?? '',
    'LineOne' => $billing['address_1'] ?? '',
    'CityName' => $billing['city'] ?? '',
    'CountryID' => $billing['countrycode'] ?? 'DE',
];
foreach ($buyerAddressFields as $tag => $val) {
    if (!empty($val)) {
        $el = $dom->createElementNS($namespaces['ram'], 'ram:' . $tag, $val);
        $buyerAddress->appendChild($el);
    }
}

if (!empty($billing['vat_2'])) {
    $buyerTaxReg = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTaxRegistration');
    $buyerParty->appendChild($buyerTaxReg);
    $vatId = $dom->createElementNS($namespaces['ram'], 'ram:ID', $billing['vat_2']);
    $vatId->setAttribute('schemeID', 'VA');
    $buyerTaxReg->appendChild($vatId);
}


// ApplicableHeaderTradeDelivery
$delivery = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableHeaderTradeDelivery');
$transaction->appendChild($delivery);


// ApplicableHeaderTradeSettlement
$settlement = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableHeaderTradeSettlement');
$transaction->appendChild($settlement);

$currencyCode = $dom->createElementNS($namespaces['ram'], 'ram:InvoiceCurrencyCode', $builder->getCurrencyCode());
$settlement->appendChild($currencyCode);

$totalTaxAmount = 0;
$totalNettoAmount = 0;

// Calculate totals dynamically from 'total' array
if (!empty($total)) {
    foreach ($total as $t) {
        $amount = $builder->getAmount($t->amount, $t->amount_currency ?? 0);
        if (isset($t->type) && $t->type == 'tax') {
            $totalTaxAmount += $amount;
        }
        if (isset($t->type) && $t->type == 'netto') {
            $totalNettoAmount += $amount;
        }
    }
}

// Generate a single Tax Subtotal based on the aggregated totals
// This avoids issues with taxrecapitulation duplicates/zeros
$rate = 0;
if ($totalNettoAmount != 0) {
    $rate = round(($totalTaxAmount / $totalNettoAmount) * 100, 2);
}

$tradeTax = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableTradeTax');
$settlement->appendChild($tradeTax);

$calculatedAmount = $dom->createElementNS($namespaces['ram'], 'ram:CalculatedAmount', number_format($totalTaxAmount, 2, '.', ''));
$tradeTax->appendChild($calculatedAmount);

$typeCode = $dom->createElementNS($namespaces['ram'], 'ram:TypeCode', 'VAT');
$tradeTax->appendChild($typeCode);

$basisAmount = $dom->createElementNS($namespaces['ram'], 'ram:BasisAmount', number_format($totalNettoAmount, 2, '.', ''));
$tradeTax->appendChild($basisAmount);

$categoryCode = $dom->createElementNS($namespaces['ram'], 'ram:CategoryCode', 'S');
$tradeTax->appendChild($categoryCode);

$ratePercent = $dom->createElementNS($namespaces['ram'], 'ram:RateApplicablePercent', number_format($rate, 2, '.', ''));
$tradeTax->appendChild($ratePercent);

$nettoTotal = 0;
$bruttoTotal = 0;
$roundingTotal = 0;

// Phoca Cart order logic for specific tax calculation setting
$orderParams = $orderData['params'] ?? null;
$tax_calculation_sales = $orderParams ? $orderParams->get('tax_calculation_sales', 0) : 0;
$tax_calculation_sales_change_subtotal = $orderParams ? $orderParams->get('tax_calculation_sales_change_subtotal', 0) : 0;

$correctedNetto = null;

if ($tax_calculation_sales == 2 && $tax_calculation_sales_change_subtotal == 1 && !empty($total)) {
    $cBrutto = 0;
    $cDbrutto = 0;
    $cTax = 0;
    $cRounding = 0;

    foreach($total as $t) {
        $amount = $builder->getAmount($t->amount, $t->amount_currency ?? 0);
        if ($t->type == 'brutto') { $cBrutto += $amount; }
        if ($t->type == 'dbrutto') { $cDbrutto += $amount; }
        if ($t->type == 'tax') { $cTax += $amount; }
        if ($t->type == 'rounding') { $cRounding += $amount; }
    }
    $correctedNetto = $cBrutto - $cDbrutto - $cTax - $cRounding;
}

if (!empty($total)) {
    foreach ($total as $t) {
        $amount = $builder->getAmount($t->amount, $t->amount_currency ?? 0);

        if (isset($t->type) && $t->type == 'brutto') {
            $bruttoTotal = $amount ?? 0;
        }
        if (isset($t->type) && $t->type == 'netto') {
            $nettoTotal = $amount ?? 0;
        }
        if (isset($t->type) && $t->type == 'rounding') {
            $roundingTotal = $amount ?? 0;
        }
    }
}

if ($correctedNetto !== null) {
    $nettoTotal = $correctedNetto;
}

if ($bruttoTotal == 0 && isset($common->total_amount)) {
    $bruttoTotal = $common->total_amount;
}

$monetarySummation = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
$settlement->appendChild($monetarySummation);

$lineTotalAmount = $dom->createElementNS($namespaces['ram'], 'ram:LineTotalAmount', number_format($nettoTotal, 2, '.', ''));
$monetarySummation->appendChild($lineTotalAmount);

$taxBasisTotalAmount = $dom->createElementNS($namespaces['ram'], 'ram:TaxBasisTotalAmount', number_format($nettoTotal, 2, '.', ''));
$monetarySummation->appendChild($taxBasisTotalAmount);

$taxTotalAmount = $dom->createElementNS($namespaces['ram'], 'ram:TaxTotalAmount', number_format($totalTaxAmount, 2, '.', ''));
$taxTotalAmount->setAttribute('currencyID', $builder->getCurrencyCode());
$monetarySummation->appendChild($taxTotalAmount);

$grandTotalAmount = $dom->createElementNS($namespaces['ram'], 'ram:GrandTotalAmount', number_format($bruttoTotal, 2, '.', ''));
$monetarySummation->appendChild($grandTotalAmount);

$duePayableAmount = $dom->createElementNS($namespaces['ram'], 'ram:DuePayableAmount', number_format($bruttoTotal, 2, '.', ''));
$monetarySummation->appendChild($duePayableAmount);


echo $dom->saveXML();
