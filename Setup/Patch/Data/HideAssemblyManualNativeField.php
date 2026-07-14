<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class HideAssemblyManualNativeField implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var CategorySetupFactory */
    private $categorySetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categorySetupFactory = $categorySetupFactory;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            $categorySetup = $this->categorySetupFactory->create([
                'setup' => $this->moduleDataSetup,
            ]);

            $attributeId = $categorySetup->getAttributeId(
                Product::ENTITY,
                AddAssemblyManualAttribute::ATTRIBUTE_CODE
            );

            if ($attributeId) {
                $categorySetup->updateAttribute(
                    Product::ENTITY,
                    AddAssemblyManualAttribute::ATTRIBUTE_CODE,
                    'is_visible',
                    0
                );
            }
        } finally {
            $this->moduleDataSetup->getConnection()->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AddAssemblyManualAttribute::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
