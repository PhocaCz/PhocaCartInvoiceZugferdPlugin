<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\FileLayout;

class PlgPciZugferdBuilder
{
    protected $orderData;
    protected $params;
    protected $currencyCode;
    protected $namespaces = [
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'xs' => 'http://www.w3.org/2001/XMLSchema',
        'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
    ];

    public function __construct(array $orderData, $params)
    {
        $this->orderData = $orderData;
        $this->params = $params;
        $this->currencyCode = $orderData['common']->currency_code ?? \PhocacartCurrency::getDefaultCurrencyCode();
    }

    public function getOrderData()
    {
        return $this->orderData;
    }

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function getNamespaces()
    {
        return $this->namespaces;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function build(): string
    {
        $layout = new FileLayout('output', __DIR__ . '/../tmpl');
        return $layout->render(['builder' => $this]);
    }

    public function getAmount($value, $valueCurrency = 0)
    {
        if (isset($valueCurrency) && $valueCurrency > 0) {
            return $valueCurrency;
        }

        $currency = \PhocacartCurrency::getCurrency(0, (int)$this->orderData['common']->id);
        $exchangeRate = $currency->exchange_rate ?? 1;

        if ($exchangeRate > 0) {
            return $value * $exchangeRate;
        }
        return $value;
    }

    public function getProfileUri(string $profile): string
    {
        $profiles = [
            'MINIMUM' => 'urn:factur-x.eu:1p0:minimum',
            'BASIC' => 'urn:factur-x.eu:1p0:basicwl',
            'EN16931' => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:en16931',
            'EXTENDED' => 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended',
        ];
        return $profiles[$profile] ?? $profiles['EN16931'];
    }
}
