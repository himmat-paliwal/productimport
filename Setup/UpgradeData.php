<?php
namespace Eastlane\Productimport\Setup;

use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

/**
 * Upgrade Data script
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * EAV setup factory
     *
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $eavSetupFactory;

    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if ($context->getVersion()
            && version_compare($context->getVersion(), '1.0.1') < 0
        ) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->addAttribute(\Magento\Catalog\Model\Category :: ENTITY, 'sewing_product', [
                'type' => 'int',
                'label' => 'Sewing Product',
                'input' => 'select',
                'source' => 'Eastlane\Productimport\Model\Config\Source\Options',
                'required' => false,
                'sort_order' => 130,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'General Information',
                "default" => "",
                "class"    => "",
                "note"       => ""
            ]);
        }

        if ($context->getVersion()
            && version_compare($context->getVersion(), '1.0.2') < 0
        ) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->addAttribute(\Magento\Catalog\Model\Category :: ENTITY, 'is_option_required', [
                'type' => 'int',
                'label' => 'Is Extra Option Required?',
                'input' => 'select',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'sort_order' => 140,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'General Information',
                "default" => "",
                "class"    => "",
                "note"       => ""
            ]);
        }

        if ($context->getVersion()
            && version_compare($context->getVersion(), '1.0.3') < 0
        ) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->addAttribute(\Magento\Catalog\Model\Category :: ENTITY, 'extra_option_type', [
                'type' => 'varchar',
                'label' => 'Set Extra Option Type',
                'input' => 'select',
                'source' => 'Eastlane\Productimport\Model\Config\Source\Optionstypes',
                'required' => false,
                'sort_order' => 140,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'General Information',
                "default" => "",
                "class"    => "",
                "note"       => ""
            ]);
        }
    }
}