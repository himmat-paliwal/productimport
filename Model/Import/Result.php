<?php
namespace Eastlane\Productimport\Model\Import;

use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\History as ModelHistory;

class Result extends \Magento\Framework\DataObject
{
    /**
     * @var \Magento\ImportExport\Model\Report\ReportProcessorInterface
     */
    protected $reportProcessor;

    /**
     * @var \Magento\ImportExport\Model\History
     */
    protected $historyModel;

    /**
     * @var \Magento\ImportExport\Helper\Report
     */
    protected $reportHelper;

    /**
     * @param \Magento\ImportExport\Model\Report\ReportProcessorInterface $reportProcessor
     * @param \Magento\ImportExport\Model\History $historyModel
     * @param \Magento\ImportExport\Helper\Report $reportHelper
     * @param array $data
     */
    public function __construct(
        \Magento\ImportExport\Model\Report\ReportProcessorInterface $reportProcessor,
        \Magento\ImportExport\Model\History $historyModel,
        \Magento\ImportExport\Helper\Report $reportHelper,
        array $data = []
    ) {
        $this->reportProcessor = $reportProcessor;
        $this->historyModel = $historyModel;
        $this->reportHelper = $reportHelper;
        parent::__construct($data);
    }

    /**
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @return string
     */
    public function createErrorReport(ProcessingErrorAggregatorInterface $errorAggregator)
    {
        $this->historyModel->loadLastInsertItem();
        $sourceFile = $this->reportHelper->getReportAbsolutePath($this->historyModel->getImportedFile());
        $writeOnlyErrorItems = true;
        
        $fileName = $this->reportProcessor->createReport($sourceFile, $errorAggregator, $writeOnlyErrorItems);
        $this->historyModel->addErrorReportFile($fileName);
        return $fileName;
    }
}