<?php
/**
 * @author GDMexico
 * @package GDMexico_ProductManual
 */

declare(strict_types=1);

namespace GDMexico\ProductManual\Plugin\Frontend;

use GDMexico\ProductManual\Setup\Patch\Data\AddAssemblyManualAttribute;
use Magento\Catalog\Block\Product\View;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class AppendManualToWarranty
{
    /**
     * Nombre en layout del bloque de cuidados y garantías.
     */
    private const TARGET_BLOCK = 'product.custom.warranty';

    /**
     * Clase CSS del enlace del manual.
     */
    private const MANUAL_LINK_CLASS = 'product-assembly-manual__link';

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Escaper $escaper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Escaper $escaper,
        StoreManagerInterface $storeManager
    ) {
        $this->escaper = $escaper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param View $subject
     * @return void
     */
    public function beforeToHtml(View $subject): void
    {
        if ($subject->getNameInLayout() !== self::TARGET_BLOCK) {
            return;
        }

        $product = $subject->getProduct();

        if (!$product) {
            return;
        }

        $manualValue = trim(
            (string) $product->getData(
                AddAssemblyManualAttribute::ATTRIBUTE_CODE
            )
        );

        if ($manualValue === '') {
            return;
        }

        $subject->setData(
            'title',
            (string) __('Cuidados, garantías y manuales')
        );
    }

    /**
     * @param View $subject
     * @param string $result
     * @return string
     */
    public function afterToHtml(
        View $subject,
        string $result
    ): string {
        if ($subject->getNameInLayout() !== self::TARGET_BLOCK) {
            return $result;
        }


        if (strpos($result, self::MANUAL_LINK_CLASS) !== false) {
            return $result;
        }

        $product = $subject->getProduct();

        if (!$product) {
            return $result;
        }

        $manualValue = trim(
            (string) $product->getData(
                AddAssemblyManualAttribute::ATTRIBUTE_CODE
            )
        );

        if ($manualValue === '') {
            return $result;
        }

        $manualUrl = $this->getManualUrl($manualValue);

        if ($manualUrl === '') {
            return $result;
        }

        $manualHtml = sprintf(
            '<div class="product-assembly-manual info-section">'
            . '<a class="%s" href="%s" target="_blank" rel="noopener noreferrer" title="%s">'
            . '<span class="product-assembly-manual__icon" aria-hidden="true"></span>'
            . '<span class="product-assembly-manual__label" style="font-weight: 400;color: #000;">%s</span>'
            . '</a>'
            . '</div>',
            self::MANUAL_LINK_CLASS,
            $this->escaper->escapeUrl($manualUrl),
            $this->escaper->escapeHtmlAttr(
                (string) __('Abrir Manual de armado')
            ),
            $this->escaper->escapeHtml(
                (string) __('Manual de armado')
            )
        );

        return $result . $manualHtml;
    }

    /**

     * @param string $manualValue
     * @return string
     */
    private function getManualUrl(string $manualValue): string
    {
        $manualValue = trim($manualValue);

        if ($manualValue === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $manualValue)) {
            $path = parse_url($manualValue, PHP_URL_PATH);

            if (!is_string($path) || $path === '') {
                return '';
            }

            $mediaPosition = strpos($path, '/media/');

            if ($mediaPosition !== false) {
                $manualValue = substr(
                    $path,
                    $mediaPosition + strlen('/media/')
                );
            } else {
                $manualValue = ltrim($path, '/');
            }
        }

        $manualValue = ltrim($manualValue, '/');

        $manualValue = (string) preg_replace(
            '#^(pub/)?media/#i',
            '',
            $manualValue
        );

        if ($manualValue === '') {
            return '';
        }

        $mediaBaseUrl = rtrim(
            $this->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        );

        return $mediaBaseUrl . '/' . $manualValue;
    }
}
