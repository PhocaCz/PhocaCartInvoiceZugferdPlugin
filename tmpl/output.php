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

    // Attributes
    $desc = $product->title ?? '';
    if (!empty($product->attributes)) {
        foreach ($product->attributes as $attr) {
            $desc .= "\n - " . $attr->attribute_title . ' ' . $attr->option_title;
        }
    }

    // Product Discounts - follow order.php sequential logic (Code 1) to get the latest item
    $orderParams = $orderData['params'] ?? null;
    $display_discount_price_product = $orderParams ? $orderParams->get('display_discount_price_product', 0) : 0;
    $discounts = $orderData['discounts'] ?? [];

    if ($display_discount_price_product == 1 && !empty($discounts[$product->product_id_key])) {
        foreach ($discounts[$product->product_id_key] as $v3) {
            $netto = $builder->getAmount($v3->netto, $v3->netto_currency ?? 0);
            $tax = $builder->getAmount($v3->tax, $v3->tax_currency ?? 0);
            if (!empty($v3->title)) {
                $desc .= ' (' . $v3->title . ')';
            }
        }
    }

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
    $productName = $dom->createElementNS($namespaces['ram'], 'ram:Name', $desc);
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

// Cart Discounts, Shipping, Payment, etc.
if (!empty($total)) {
    foreach ($total as $t) {
        $skipTypes = ['netto', 'brutto', 'tax', 'rounding', 'dbrutto'];
        if (in_array($t->type, $skipTypes)) {
            continue;
        }
        if ($t->amount == 0 && ($t->amount_currency ?? 0) == 0) {
            continue;
        }

        $lineNetto = $builder->getAmount($t->amount, $t->amount_currency ?? 0);
        $lineTaxRate = $t->tax_rate ?? 0;

        $lineItem = $dom->createElementNS($namespaces['ram'], 'ram:IncludedSupplyChainTradeLineItem');
        $transaction->appendChild($lineItem);

        $lineDoc = $dom->createElementNS($namespaces['ram'], 'ram:AssociatedDocumentLineDocument');
        $lineItem->appendChild($lineDoc);
        $lineId = $dom->createElementNS($namespaces['ram'], 'ram:LineID', (string)$lineNumber);
        $lineDoc->appendChild($lineId);

        $tradeProduct = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTradeProduct');
        $lineItem->appendChild($tradeProduct);
        $productName = $dom->createElementNS($namespaces['ram'], 'ram:Name', $t->title ?? '');
        $tradeProduct->appendChild($productName);

        $lineTradeAgreement = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedLineTradeAgreement');
        $lineItem->appendChild($lineTradeAgreement);

        $netPrice = $dom->createElementNS($namespaces['ram'], 'ram:NetPriceProductTradePrice');
        $lineTradeAgreement->appendChild($netPrice);
        $chargeAmount = $dom->createElementNS($namespaces['ram'], 'ram:ChargeAmount', number_format($lineNetto, 2, '.', ''));
        $netPrice->appendChild($chargeAmount);

        $lineTradeDelivery = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedLineTradeDelivery');
        $lineItem->appendChild($lineTradeDelivery);
        $billedQuantity = $dom->createElementNS($namespaces['ram'], 'ram:BilledQuantity', '1');
        $billedQuantity->setAttribute('unitCode', 'C62');
        $lineTradeDelivery->appendChild($billedQuantity);

        $lineTradeSettlement = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedLineTradeSettlement');
        $lineItem->appendChild($lineTradeSettlement);

        $applicableTradeTax = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableTradeTax');
        $lineTradeSettlement->appendChild($applicableTradeTax);
        $typeCode = $dom->createElementNS($namespaces['ram'], 'ram:TypeCode', 'VAT');
        $applicableTradeTax->appendChild($typeCode);
        $categoryCode = $dom->createElementNS($namespaces['ram'], 'ram:CategoryCode', 'S');
        $applicableTradeTax->appendChild($categoryCode);
        $ratePercent = $dom->createElementNS($namespaces['ram'], 'ram:RateApplicablePercent', number_format($lineTaxRate, 2, '.', ''));
        $applicableTradeTax->appendChild($ratePercent);

        $monetarySummation = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTradeSettlementLineMonetarySummation');
        $lineTradeSettlement->appendChild($monetarySummation);
        $lineTotalAmount = $dom->createElementNS($namespaces['ram'], 'ram:LineTotalAmount', number_format($lineNetto, 2, '.', ''));
        $monetarySummation->appendChild($lineTotalAmount);

        $lineNumber++;
    }
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

$totalTaxSum = 0;
$totalNetBasisSum = 0;

if (!empty($taxrecapitulation)) {
    foreach ($taxrecapitulation as $v) {
        if ($v->type == 'tax') {
            $currRate = (float)($v->tax_rate ?? 0);
            
            // Try to get original rate from database to avoid rounding errors (19.98 vs 20)
            if ($currRate == 0 && (int)$v->item_id > 0) {
                // Check if it's a country or region specific tax override
                if ((int)$v->item_id_c > 0) {
                    $db = Factory::getDbo();
                    $q = $db->getQuery(true)->select('tax_rate')->from('#__phocacart_tax_countries')->where('id = ' . (int)$v->item_id_c);
                    $db->setQuery($q);
                    $currRate = (float)$db->loadResult();
                } else if ((int)$v->item_id_r > 0) {
                    $db = Factory::getDbo();
                    $q = $db->getQuery(true)->select('tax_rate')->from('#__phocacart_tax_regions')->where('id = ' . (int)$v->item_id_r);
                    $db->setQuery($q);
                    $currRate = (float)$db->loadResult();
                } else {
                    $db = Factory::getDbo();
                    $q = $db->getQuery(true)->select('tax_rate')->from('#__phocacart_taxes')->where('id = ' . (int)$v->item_id);
                    $db->setQuery($q);
                    $currRate = (float)$db->loadResult();
                }
            }

            // Fallback to calculation if still 0 but amounts exist (legitimate 0% is fine, but 19.98% error isn't)
            if ($currRate == 0 && $v->amount_netto != 0 && $v->amount_tax != 0) {
                $currRate = round(($v->amount_tax / $v->amount_netto) * 100, 2);
            }

            $tradeTax = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableTradeTax');
            $settlement->appendChild($tradeTax);

            $calculatedAmount = $dom->createElementNS($namespaces['ram'], 'ram:CalculatedAmount', number_format($v->amount_tax, 2, '.', ''));
            $tradeTax->appendChild($calculatedAmount);

            $typeCode = $dom->createElementNS($namespaces['ram'], 'ram:TypeCode', 'VAT');
            $tradeTax->appendChild($typeCode);

            $basisAmount = $dom->createElementNS($namespaces['ram'], 'ram:BasisAmount', number_format($v->amount_netto, 2, '.', ''));
            $tradeTax->appendChild($basisAmount);

            $categoryCode = $dom->createElementNS($namespaces['ram'], 'ram:CategoryCode', 'S');
            $tradeTax->appendChild($categoryCode);

            $ratePercent = $dom->createElementNS($namespaces['ram'], 'ram:RateApplicablePercent', number_format($currRate, 2, '.', ''));
            $tradeTax->appendChild($ratePercent);

            $totalTaxSum += $v->amount_tax;
            $totalNetBasisSum += $v->amount_netto;
        }
    }
} else {
    // Fallback logic
    $totalTaxAmountFallback = 0;
    $totalNettoAmountFallback = 0;
    foreach ($total as $t) {
        $amount = $builder->getAmount($t->amount, $t->amount_currency ?? 0);
        if (isset($t->type) && $t->type == 'tax') { $totalTaxAmountFallback += $amount; }
        if (isset($t->type) && $t->type == 'netto') { $totalNettoAmountFallback += $amount; }
    }
    $totalTaxSum = $totalTaxAmountFallback;
    $totalNetBasisSum = $totalNettoAmountFallback;

    $rate = 0;
    if ($totalNetBasisSum != 0) {
        $rate = round(($totalTaxSum / $totalNetBasisSum) * 100, 2);
    }

    $tradeTax = $dom->createElementNS($namespaces['ram'], 'ram:ApplicableTradeTax');
    $settlement->appendChild($tradeTax);
    $tradeTax->appendChild($dom->createElementNS($namespaces['ram'], 'ram:CalculatedAmount', number_format($totalTaxSum, 2, '.', '')));
    $tradeTax->appendChild($dom->createElementNS($namespaces['ram'], 'ram:TypeCode', 'VAT'));
    $tradeTax->appendChild($dom->createElementNS($namespaces['ram'], 'ram:BasisAmount', number_format($totalNetBasisSum, 2, '.', '')));
    $tradeTax->appendChild($dom->createElementNS($namespaces['ram'], 'ram:CategoryCode', 'S'));
    $tradeTax->appendChild($dom->createElementNS($namespaces['ram'], 'ram:RateApplicablePercent', number_format($rate, 2, '.', '')));
}

// Recalculate Sum of EVERYTHING that was actually put into lines to fulfill Factur-X/ZUGFeRD validation:
// LineTotalAmount must equal sum of line net amounts.
$lineSumTotal = 0;
foreach ($products as $product) {
    $pNetto = $builder->getAmount($product->netto, $product->netto_currency ?? 0);
    if ($display_discount_price_product == 1 && !empty($discounts[$product->product_id_key])) {
        foreach ($discounts[$product->product_id_key] as $v3) {
            $pNetto = $builder->getAmount($v3->netto, $v3->netto_currency ?? 0);
        }
    }
    $lineSumTotal += ($pNetto * $product->quantity);
}
// Add shipping, payment items from total array
if (!empty($total)) {
    foreach ($total as $t) {
        $skipTypes = ['netto', 'brutto', 'tax', 'rounding', 'dbrutto'];
        if (in_array($t->type, $skipTypes)) { continue; }
        if ($t->amount == 0 && ($t->amount_currency ?? 0) == 0) { continue; }
        $lineSumTotal += $builder->getAmount($t->amount, $t->amount_currency ?? 0);
    }
}

$bruttoTotal = 0;
$roundingTotal = 0;

if (!empty($total)) {
    foreach ($total as $t) {
        $amount = $builder->getAmount($t->amount, $t->amount_currency ?? 0);
        if (isset($t->type) && $t->type == 'brutto') {
            $bruttoTotal = $amount;
        }
        if (isset($t->type) && $t->type == 'rounding') {
            $roundingTotal = $amount;
        }
    }
}

if ($bruttoTotal == 0 && isset($common->total_amount)) {
    $bruttoTotal = $common->total_amount;
}

$monetarySummation = $dom->createElementNS($namespaces['ram'], 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
$settlement->appendChild($monetarySummation);

$lineTotalAmountNode = $dom->createElementNS($namespaces['ram'], 'ram:LineTotalAmount', number_format($lineSumTotal, 2, '.', ''));
$monetarySummation->appendChild($lineTotalAmountNode);

$taxBasisTotalAmountNode = $dom->createElementNS($namespaces['ram'], 'ram:TaxBasisTotalAmount', number_format($lineSumTotal, 2, '.', ''));
$monetarySummation->appendChild($taxBasisTotalAmountNode);

$taxTotalAmountNode = $dom->createElementNS($namespaces['ram'], 'ram:TaxTotalAmount', number_format($totalTaxSum, 2, '.', ''));
$taxTotalAmountNode->setAttribute('currencyID', $builder->getCurrencyCode());
$monetarySummation->appendChild($taxTotalAmountNode);

$grandTotalAmountNode = $dom->createElementNS($namespaces['ram'], 'ram:GrandTotalAmount', number_format($bruttoTotal, 2, '.', ''));
$monetarySummation->appendChild($grandTotalAmountNode);

$duePayableAmountNode = $dom->createElementNS($namespaces['ram'], 'ram:DuePayableAmount', number_format($bruttoTotal, 2, '.', ''));
$monetarySummation->appendChild($duePayableAmountNode);


echo $dom->saveXML();
