<?php
namespace Eastlane\Productimport\Model\Import\Product;

class Option extends \Magento\CatalogImportExport\Model\Import\Product\Option
{
    /**
     * Retrieve option data
     *
     * @param array $rowData
     * @param int $productId
     * @param int $optionId
     * @param string $type
     * @return array
     */
    protected function _getOptionData(array $rowData, $productId, $optionId, $type)
    {
        $actualData = $this->_parseCustomOptions($rowData);
        //print_r($actualData);exit;
        $optionData = [
            'option_id' => $optionId,
            'sku' => '',
            'max_characters' => 0,
            'file_extension' => null,
            'image_size_x' => 0,
            'image_size_y' => 0,
            'product_id' => $productId,
            'type' => $type,
            'is_require' => empty($rowData[self::COLUMN_IS_REQUIRED]) ? 0 : 1,
            'sort_order' => empty($rowData[self::COLUMN_SORT_ORDER]) ? 0 : abs($rowData[self::COLUMN_SORT_ORDER]),
            'unit' => $actualData['custom_options'][$rowData[self::COLUMN_TITLE]][0]['unit'],
            'default_value' => $actualData['custom_options'][$rowData[self::COLUMN_TITLE]][0]['default_value'],
            'option_identify_class' => $actualData['custom_options'][$rowData[self::COLUMN_TITLE]][0]['option_identify_class'],
            'is_numeric' => isset($actualData['custom_options'][$rowData[self::COLUMN_TITLE]][0]['is_numeric']) ? $actualData['custom_options'][$rowData[self::COLUMN_TITLE]][0]['is_numeric'] : 0 ,
        ];


        if (!$this->_isRowHasSpecificType($type)) {
            // simple option may have optional params
            foreach ($this->_specificTypes[$type] as $paramSuffix) {
                if (isset($rowData[self::COLUMN_PREFIX . $paramSuffix])) {
                    $data = $rowData[self::COLUMN_PREFIX . $paramSuffix];

                    if (array_key_exists($paramSuffix, $optionData)) {
                        $optionData[$paramSuffix] = $data;
                    }
                }
            }
        }
        return $optionData;
    }

}