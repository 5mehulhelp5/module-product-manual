<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Plugin\Frontend;

use GDMexico\ProductManual\Setup\Patch\Data\AddAssemblyManualAttribute;
use Magento\Catalog\Block\Product\View;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class AppendManualToWarranty
{
    private const TARGET_BLOCK = 'product.custom.warranty';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper
    ) {
    }

    public function beforeToHtml(View $subject): void
    {
        if ($subject->getNameInLayout() !== self::TARGET_BLOCK) {
            return;
        }

        $subject->setData('title', (string)__('Cuidados, garantías y manuales'));
    }

    public function afterToHtml(View $subject, string $result): string
    {
        if ($subject->getNameInLayout() !== self::TARGET_BLOCK) {
            return $result;
        }

        $product = $subject->getProduct();
        if (!$product) {
            return $result;
        }

        $value = trim((string)$product->getData(AddAssemblyManualAttribute::ATTRIBUTE_CODE));
        if ($value === '') {
            return $result;
        }

        $url = preg_match('#^https?://#i', $value)
            ? $value
            : rtrim(
                $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                '/'
            ) . '/' . ltrim($value, '/');

        $manualHtml = sprintf(
            '<div class="product-assembly-manual info-section">'
            . '<a class="product-assembly-manual__link" href="%s" target="_blank" rel="noopener noreferrer">'
            . '<span class="product-assembly-manual__icon" aria-hidden="true">&#128196;</span>'
            . '<span>%s</span>'
            . '</a>'
            . '</div>',
            $this->escaper->escapeUrl($url),
            $this->escaper->escapeHtml((string)__('Manual de armado'))
        );

        return $result . $manualHtml;
    }
}
