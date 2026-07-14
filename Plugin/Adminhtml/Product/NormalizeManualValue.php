<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Plugin\Adminhtml\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Save;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class NormalizeManualValue
{
    private const ATTRIBUTE_CODE = 'assembly_manual';

    /** @var RequestInterface */
    private $request;

    /** @var ProductAction */
    private $productAction;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        RequestInterface $request,
        ProductAction $productAction,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->productAction = $productAction;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Guarda el producto normalmente y posteriormente persiste
     * assembly_manual directamente mediante ProductAction.
     */
    public function aroundExecute(
        Save $subject,
        callable $proceed
    ): ResultInterface {
        $productData = $this->request->getParam('product');

        $manualWasSubmitted = false;
        $manualValue = null;

        if (is_array($productData)) {
            $manualValue = $this->findManualValue(
                $productData,
                $manualWasSubmitted
            );
        }

        $normalizedValue = $manualWasSubmitted
            ? $this->normalizeValue($manualValue)
            : null;

        /*
         * Primero permitimos que Magento guarde normalmente
         * todos los datos del producto.
         */
        $result = $proceed();

        /*
         * Si el formulario no envió assembly_manual, no modificamos
         * el valor existente.
         */
        if (!$manualWasSubmitted || $normalizedValue === null) {
            $this->logger->info(
                '[ProductManual] El formulario no envió assembly_manual; no se modifica.'
            );

            return $result;
        }

        try {
            $productId = $this->resolveProductId($productData);

            if ($productId <= 0) {
                $this->logger->error(
                    '[ProductManual] No se pudo identificar el producto después del guardado.',
                    [
                        'request_id' => $this->request->getParam('id'),
                        'sku' => is_array($productData)
                            ? ($productData['sku'] ?? null)
                            : null,
                    ]
                );

                return $result;
            }

            $storeId = (int) $this->request->getParam('store', 0);

            $this->productAction->updateAttributes(
                [$productId],
                [
                    self::ATTRIBUTE_CODE => $normalizedValue,
                ],
                $storeId
            );

            $this->logger->info(
                '[ProductManual] Manual guardado mediante ProductAction.',
                [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'value' => $normalizedValue,
                ]
            );
        } catch (\Throwable $exception) {
            $this->logger->critical(
                '[ProductManual] Error guardando assembly_manual.',
                [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]
            );
        }

        return $result;
    }

    /**
     * Busca assembly_manual en cualquier nivel del arreglo.
     */
    private function findManualValue(
        array $data,
        bool &$wasSubmitted
    ) {
        if (array_key_exists(self::ATTRIBUTE_CODE, $data)) {
            $wasSubmitted = true;

            return $data[self::ATTRIBUTE_CODE];
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $foundValue = $this->findManualValue(
                $value,
                $wasSubmitted
            );

            if ($wasSubmitted) {
                return $foundValue;
            }
        }

        return null;
    }

    /**
     * Obtiene el ID tanto para productos existentes como nuevos.
     */
    private function resolveProductId(?array $productData): int
    {
        $productId = (int) $this->request->getParam('id');

        if ($productId > 0) {
            return $productId;
        }

        if (!is_array($productData)) {
            return 0;
        }

        $sku = trim((string) ($productData['sku'] ?? ''));

        if ($sku === '') {
            return 0;
        }

        try {
            return (int) $this->productRepository
                ->get($sku, false, null, true)
                ->getId();
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[ProductManual] No fue posible resolver el producto por SKU.',
                [
                    'sku' => $sku,
                    'message' => $exception->getMessage(),
                ]
            );

            return 0;
        }
    }

    /**
     * Convierte la respuesta del fileUploader en una ruta string.
     */
    private function normalizeValue($value): string
    {
        if (is_string($value)) {
            return $this->sanitizePath($value);
        }

        if (!is_array($value) || $value === []) {
            return '';
        }

        if (!empty($value['file'])) {
            return $this->sanitizePath(
                (string) $value['file']
            );
        }

        if (!empty($value['url'])) {
            return $this->sanitizePath(
                (string) $value['url']
            );
        }

        $firstItem = reset($value);

        if (!is_array($firstItem)) {
            return '';
        }

        if (!empty($firstItem['file'])) {
            return $this->sanitizePath(
                (string) $firstItem['file']
            );
        }

        if (!empty($firstItem['url'])) {
            return $this->sanitizePath(
                (string) $firstItem['url']
            );
        }

        if (!empty($firstItem['name'])) {
            return $this->sanitizePath(
                (string) $firstItem['name']
            );
        }

        return '';
    }

    private function sanitizePath(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            $path = parse_url($value, PHP_URL_PATH);

            if (is_string($path)) {
                $mediaPosition = strpos($path, '/media/');

                if ($mediaPosition !== false) {
                    return ltrim(
                        substr($path, $mediaPosition + 7),
                        '/'
                    );
                }
            }

            return $value;
        }

        return ltrim($value, '/');
    }
}