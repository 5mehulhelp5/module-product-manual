<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class HideAssemblyManualNativeField implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CategorySetupFactory $categorySetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

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

        $this->moduleDataSetup->getConnection()->endSetup();

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