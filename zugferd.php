<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');

class plgPCIZugferd extends CMSPlugin
{
    protected $name = 'zugferd';
    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function onPCIrenderElectronicInvoice($orderData, $eventData)
    {
        if (!isset($eventData['pluginname']) || $eventData['pluginname'] != $this->name) {
            return false;
        }

        if (empty($orderData)) {
            return false;
        }

        require_once __DIR__ . '/helpers/ZugferdBuilder.php';

        $builder = new PlgPciZugferdBuilder($orderData, $this->params);
        $xml = $builder->build();

        return [
            'content' => $xml,
            'contentType' => 'application/xml',
            'filename' => 'invoice-zugferd-' . ($orderData['common']->invoice_number ?? $orderData['common']->id) . '.xml',
        ];
    }

    public function onPCIgetElectronicInvoiceIcons($orderId, $eventData)
    {
        $order = $eventData['order'] ?? null;
        if (!$order || empty($order->invoice_number)) {
            return false;
        }

        $app = Factory::getApplication();
        $s   = \PhocacartRenderStyle::getStyles();
        if ($app->isClient('administrator')) {
            $link = Route::_('index.php?option=com_phocacart&view=phocacartorderview&tmpl=component&format=raw&id=' . (int)$orderId . '&type=5&subtype=zugferd');

            $icon = '<a href="' . $link . '" class="btn btn-secondary btn-small btn-xs ph-btn ph-order ph-e-invoice-btn ph-orders-btn" role="button" title="' . Text::_('PLG_PCI_ZUGFERD_DOWNLOAD') . '">';
            $icon .= '<span class="icon-download ph-icon-info" title="' . Text::_('PLG_PCI_ZUGFERD_DOWNLOAD') . '"></span>';
            $icon .= '<span class="ph-icon-info-txt">' . Text::_('PLG_PCI_ZUGFERD_LABEL') . '</span>';
            $icon .= '</a>';


        } else {
            $link = Route::_('index.php?option=com_phocacart&view=order&tmpl=component&format=raw&id=' . (int)$orderId . '&type=5&subtype=zugferd');

            $icon = '<a href="' . $link . '" class="' . $s['c']['btn.btn-danger.btn-sm'] . ' ph-btn ph-orders-btn" role="button" title="' . Text::_('PLG_PCI_ZUGFERD_DOWNLOAD') . '">' . PhocacartRenderIcon::icon($s['i']['download']. ' ph-icon-download', 'title="' . Text::_('PLG_PCI_ZUGFERD_DOWNLOAD') . '"') . '<br /><span class="ph-icon-pdf-text">' . Text::_('PLG_PCI_ZUGFERD_LABEL') . '</span></a>';
        }

        return ['icon' => $icon];
    }
}

