<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Block\Product;

use GDMexico\ProductManual\Setup\Patch\Data\AddAssemblyManualAttribute;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class Manual extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product')
            ?: $this->registry->registry('product');

        return $product instanceof Product ? $product : null;
    }

    public function getManualUrl(): ?string
    {
        $product = $this->getProduct();
        if (!$product) {
            return null;
        }

        $value = trim((string)$product->getData(AddAssemblyManualAttribute::ATTRIBUTE_CODE));
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . ltrim($value, '/');
    }
}
