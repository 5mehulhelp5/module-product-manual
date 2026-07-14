<?php
declare(strict_types=1);

namespace GDMexico\ProductManual\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddAssemblyManualAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'assembly_manual';

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

        $attributeId = $categorySetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE);
        if (!$attributeId) {
            $categorySetup->addAttribute(
                Product::ENTITY,
                self::ATTRIBUTE_CODE,
                [
                    'type' => 'varchar',
                    'label' => 'Manual de armado',
                    'input' => 'text',
                    'required' => false,
                    'sort_order' => 250,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => false,
                    'user_defined' => true,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'unique' => false,
                    'group' => 'Product Details',
                    'note' => 'Archivo PDF mostrado como Manual de armado en la ficha del producto.',
                ]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
