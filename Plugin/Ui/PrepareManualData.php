<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Plugin\Ui;

use GDMexico\ProductManual\Setup\Patch\Data\AddAssemblyManualAttribute;
use Magento\Catalog\Ui\DataProvider\Product\Form\ProductDataProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class PrepareManualData
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Filesystem */
    private $filesystem;

    public function __construct(
        StoreManagerInterface $storeManager,
        Filesystem $filesystem
    ) {
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
    }

    public function afterGetData(ProductDataProvider $subject, array $data): array
    {
        $mediaBaseUrl = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        );
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

        foreach ($data as $entityId => &$entityData) {
            if (!isset($entityData['product']) || !is_array($entityData['product'])) {
                continue;
            }

            $value = $entityData['product'][AddAssemblyManualAttribute::ATTRIBUTE_CODE] ?? '';
            if (is_array($value) || trim((string)$value) === '') {
                continue;
            }

            $file = ltrim((string)$value, '/');
            $entityData['product'][AddAssemblyManualAttribute::ATTRIBUTE_CODE] = [[
                'name' => basename($file),
                'file' => $file,
                'url' => preg_match('#^https?://#i', $file)
                    ? $file
                    : $mediaBaseUrl . '/' . $file,
                'size' => $mediaDirectory->isFile($file)
                    ? (int)$mediaDirectory->stat($file)['size']
                    : 0,
                'type' => 'application/pdf',
            ]];
        }
        unset($entityData);

        return $data;
    }
}
