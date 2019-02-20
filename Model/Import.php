<?php
namespace Eastlane\Productimport\Model;

class Import extends \Magento\ImportExport\Model\Import
{
    /**
     * Move uploaded file and create source adapter instance.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string Source file path
     */
    public function uploadSource()
    {
        $entity = $this->getEntity();
        $extension = 'csv';
        $sourceFile = $this->getWorkingDir() . $entity;

        $sourceFile .= '.' . $extension;
        $sourceFileRelative = $this->_varDirectory->getRelativePath($sourceFile);
        $this->_removeBom($sourceFile);
        $this->createHistoryReport($sourceFileRelative, $entity, $extension);

        try {
            $this->_getSourceAdapter($sourceFile);
        } catch (\Exception $e) {
            $this->_varDirectory->delete($sourceFileRelative);
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
        return $sourceFile;
    }
}