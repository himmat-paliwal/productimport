<?php

namespace Eastlane\Productimport\Model\Config\Backend;

class UploadFile extends \Magento\Config\Model\Config\Backend\File
{
    /**
     * @return string[]
     */
    protected function _getAllowedExtensions() {
        return ['csv'];
    }
}