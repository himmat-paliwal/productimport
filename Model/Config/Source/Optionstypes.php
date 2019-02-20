<?php

namespace Eastlane\Productimport\Model\Config\Source;

use Magento\Eav\Model\ResourceModel\Entity\Attribute\OptionFactory;
use Magento\Framework\DB\Ddl\Table;

/**
 * Custom Attribute Renderer
 *
 * @author      Webkul Core Team <support@webkul.com>
 */
class Optionstypes extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * @param OptionFactory $optionFactory
     */
    /*public function __construct(OptionFactory $optionFactory)
    {
        $this->optionFactory = $optionFactory;
        //you can use this if you want to prepare options dynamically
    }*/

    /**
     * Get all options
     *
     * @return array
     */
    public function getAllOptions()
    {
        /* your Attribute options list*/
        $this->_options=[ 
            ['label'=>'Drop-down', 'value'=>'select'],
            ['label'=>'Radio Buttons', 'value'=>'radio'],
            ['label'=>'Checkbox', 'value'=>'checkbox'],
            ['label'=>'Multiple Select', 'value'=>'multi'],
        ];
        return $this->_options;
    }


    /**
     * Get a text for option value
     *
     * @param string|integer $value
     * @return string|bool
     */
    public function getOptionText($value)
    {
        foreach ($this->getAllOptions() as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }

    /**
     * Retrieve flat column definition
     *
     * @return array
     */
    public function getFlatColumns()
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        return [
            $attributeCode => [
                'unsigned' => false,
                'default' => null,
                'extra' => null,
                'type' => Table::TYPE_INTEGER,
                'nullable' => true,
                'comment' => 'Custom Attribute Options Type ' . $attributeCode . ' column',
            ],
        ];
    }
}