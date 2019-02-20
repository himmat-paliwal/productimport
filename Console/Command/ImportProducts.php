<?php
namespace Eastlane\Productimport\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\ImportExport\Model\Import\Adapter as ImportAdapter;
use Eastlane\Productimport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;


class ImportProducts extends \Symfony\Component\Console\Command\Command
{
    /**
     * Media behavior option
     */
    const INPUT_KEY_MEDIA_BEHAVIOR = 'media';

    /**
     * Media path option
     */
    const INPUT_KEY_MEDIA_PATH = 'media_path';


    /**
     * Limit view errors
     */
    const LIMIT_ERRORS_MESSAGE = 100;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    /**
     * CSV Processor
     *
     * @var \Magento\Framework\File\Csv
     */
    protected $csvProcessor;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $_filesystem;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory
     */
    protected $attributeCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeOptionManagementInterface
     */
    protected $productAttributeOptionManagement;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor
     */
    protected $categoryProcessor;

    /**
     * EAV setup factory
     *
     * @var EavSetupFactory
     */
    private $_eavSetupFactory;

    /**
     * @var Import\Result
     */
    protected $_importResult;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_localeDate;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var array
     */
    protected $_columns = [];

    /**
     * @var array
     */
    protected $_excludeAttributeToCreate = [
        'categories',
        'qty'
    ];

    private $_excludeFields = [
        'product_group',
        'configurable_attribute',
        'parent_product',
        'extra_options',
        'extra_option_product',
        'media'
        //'url_key'
    ];


    private $_defaultFields = [
        'store_view_code' => '',
        'attribute_set_code' => 'Default',
        'product_type' => 'simple',
        'product_websites' => 'base',
        'visibility' => 'Catalog, Search',
        'display_product_options_in' => 'Block after Info Column',
        'website_id' => 0,
        'tax_class_name' => 'Normal',
        'product_online' => 1,
        'is_in_stock' => 1,
        'is_qty_decimal' => 1,
        'shipping_class' => 'Small',
        'bundle_sku_type' => '',
        'bundle_price_type' => '',
        'bundle_price_view' => '',
        'bundle_weight_type' => '',
        'bundle_values' => '',
        'custom_options' => '',
        'sewing_article_type' => '',
        'shipment_type' => '',
        'configurable_variations' => '',
        'news_from_date' => '',
        'news_to_date' => '',
        'base_image' => '',
        'small_image' => '',
        'thumbnail_image' => '',
        'additional_images' => ''
    ];

    private $urlRewrites = [];


    /**
     * @var array
     */
    private $importData = [];

    /**
     * @var array
     */
    private $_loadedAttributeOptions = [];

    /**
     * @var array
     */
    private $_sewingProducts = [];

    /**
     * @var Import
     */
    private $import;

    private $_productRepositoryInterface;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexCollectionFactory;

    private $productFactory;


    private $_bundleNamePostfix = [
        'TGGL' => 'Gardinlängd',
        'TGOL' => 'Öljettlängd',
        'TGHI' => 'Hissgardin',
        'TGPA' => 'Panelgardin',
        'TGSL' => 'Slät gardinkappa',
        'TGVE' => 'Veckad gardinkappa',
        'TGWA' => 'Wavegardin'
    ];

    private $_bundleArticalType = [
        'TGGL' => 'Gardinlängd(TGGL)',
        'TGOL' => 'Öljettlängd(TGOL)',
        'TGHI' => 'Hissgardin(TGHI)',
        'TGPA' => 'Panelgardin(TGPA)',
        'TGSL' => 'Slät kappa(TGSL)',
        'TGVE' => 'Veckad kapa(TGVE)',
        'TGWA' => 'Wave gardin(TGWA)'
    ];

    private $_bundleCategoryPath = [
        'TGGL' => 'Default category/Gardiner/Gardinlängder',
        'TGOL' => 'Default category/Gardiner/Öljettgardiner',
        'TGHI' => 'Default category/Gardiner/Hissgardiner',
        'TGPA' => 'Default category/Gardiner/Panelgardiner',
        'TGSL' => 'Default category/Gardiner/Slät gardinkappa',
        'TGVE' => 'Default category/Gardiner/Veckad gardinkappa',
        'TGWA' => 'Default category/Gardiner/Wavegardiner'
    ];

    private $_customOptionString = [
        'field' => 'name=%s,type=field,required=%d,price=%02.4f,price_type=%s,sku=%s,unit=%d,default_value=%d,option_identify_class=%s,is_numeric=%d,max_characters=%d,file_extension=,image_size_x=0,image_size_y=0',
        'select' => 'name=%s,type=%s,required=%d,price=%02.4f,price_type=%s,sku=%s,unit=%d,default_value=%d,option_identify_class=%s,file_extension=,image_size_x=0,image_size_y=0,option_title=%s'

    ];

    private $_bundleValueString = 'name=%s,type=%s,required=%d,sku=%s,price=%02.4f,default=%d,default_qty=%02.4f,price_type=%s,user_defined=%d';

    private $_configurableProductsData = [];

    private $_mediaPath = 'pub/media/import';

    /**
     * @var boolean
     */
    private $_canImportMedia = false;

    /**
     * @var array
     */
    private $_allImages = [];

    private $_rootDirectory;

    private $_existingProductSkus = [];

    private $tggl_addons, $tgol_addons, $tghi_addons, $tgpa_addons, $tgsl_addons, $tgve_addons, $tgwa_addons = [];

    private $_url_key = [];

    private $_curtainname = [];

    private $_optionTypeAttributes = [];


    /**
     * ImportProducts constructor.
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\File\Csv $csvProcessor
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory
     * @param \Magento\Catalog\Api\ProductAttributeOptionManagementInterface $productAttributeOptionManagement
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param EavSetupFactory $eavSetupFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localDate
     * @param \Psr\Log\LoggerInterface $logger
     */

    public function __construct(
        \Magento\Framework\App\State $appState,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory,
        \Magento\Catalog\Api\ProductAttributeOptionManagementInterface $productAttributeOptionManagement,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexCollectionFactory = null,
        EavSetupFactory $eavSetupFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localDate,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->appState = $appState;
        $this->_objectManager = $objectManager;
        $this->csvProcessor = $csvProcessor;
        $this->_filesystem = $filesystem;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->productAttributeOptionManagement = $productAttributeOptionManagement;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryProcessor = $categoryProcessor;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->_eavSetupFactory = $eavSetupFactory;
        $this->_localeDate = $localDate;
        $this->_logger = $logger;
        $this->_productRepositoryInterface = $productRepositoryInterface;
        $this->productFactory = $productFactory;
        $this->indexCollectionFactory = $indexCollectionFactory;
        parent::__construct();
    }

    private function _init()
    {
        $urlCollection = $this->_objectManager->create(\Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection::class);
        $connection = $urlCollection->getConnection();
        $productTable = $connection->getTableName('catalog_product_entity');

        $select = $urlCollection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns(['request_path'])
            ->join(
                ['e' => $productTable],
                'e.entity_id = main_table.entity_id',
                ['sku']
            );

        $this->urlRewrites = $connection->fetchPairs($select);

        $select = $connection->select();
        $select->from(
            ['e' => $productTable],
            ['sku']
        );
        $this->_existingProductSkus = $connection->fetchCol($select);

        foreach ($this->_bundleArticalType as $bundleType => $name) {
            $categories = $this->categoryCollectionFactory->create();
            $categories->addAttributeToSelect('*');
            switch ($bundleType) {
                case 'TGGL':
                    $categories->addAttributeToFilter('sewing_product', '1');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $this->tggl_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired(), 'extra_option_type' => $category->getExtraOptionType());
                                // $this->tggl_addons['category'][$category->getName()]['is_option_required']=$category->getIsOptionRequired();
                                $tggl_addons = $category->getProductCollection();
                                foreach ($tggl_addons as $tggl_addon) {
                                    $this->tggl_addons['products'][$category->getName()][] = $tggl_addon->getSku();
                                    $this->tggl_addons['products'][$tggl_addon->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                    break;
                case 'TGOL':
                    $categories->addAttributeToFilter('sewing_product', '2');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $this->tgol_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired());
                                $tgol_addons = $category->getProductCollection();
                                foreach ($tgol_addons as $tgol_addon) {
                                    $this->tgol_addons['products'][$category->getName()][] = $tgol_addon->getSku();
                                    $this->tgol_addons['products'][$tgol_addon->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                    break;
                case 'TGHI':
                    $categories->addAttributeToFilter('sewing_product', '3');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $this->tghi_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired());
                                $tghi_addons = $category->getProductCollection();
                                foreach ($tghi_addons as $tghi_addon) {
                                    $this->tghi_addons['products'][$category->getName()] = $tghi_addon->getSku();
                                    $this->tghi_addons['products'][$tghi_addon->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                    break;
                case 'TGPA':
                    $categories->addAttributeToFilter('sewing_product', '4');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $tgpa_addons = $category->getProductCollection();
                                $this->tgpa_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired());
                                foreach ($tgpa_addons as $tgpa_addon) {
                                    $this->tgpa_addons['products'][$category->getName()] = $tgpa_addon->getSku();
                                    $this->tgpa_addons['products'][$tgpa_addons->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                    break;
                case 'TGSL':
                    $categories->addAttributeToFilter('sewing_product', '5');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $tgsl_addons = $category->getProductCollection();
                                $this->tgsl_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired());
                                foreach ($tgsl_addons as $tgsl_addon) {
                                    $this->tgsl_addons['products'][$category->getName()] = $tgsl_addon->getSku();
                                    $this->tgsl_addons['products'][$tgsl_addons->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                    break;
                case 'TGVE':
                    $categories->addAttributeToFilter('sewing_product', '6');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $this->tgve_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired());
                                $tgve_addons = $category->getProductCollection();
                                foreach ($tgve_addons as $tgve_addon) {
                                    $this->tgve_addons['products'][$category->getName()][] = $tgve_addon->getSku();
                                    $this->tgve_addons['products'][$tgve_addon->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                    break;
              
                case 'TGWA':
                    $categories->addAttributeToFilter('sewing_product', '7');
                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            if ($category->getProductCollection()->count()) {
                                $this->tgwa_addons['category'][] = array($category->getName(), 'is_option_required' => $category->getIsOptionRequired(), 'extra_option_type' => $category->getExtraOptionType());
                                // $this->tggl_addons['category'][$category->getName()]['is_option_required']=$category->getIsOptionRequired();
                                $tgwa_addons = $category->getProductCollection();
                                foreach ($tgwa_addons as $tgwa_addon) {
                                    $this->tgwa_addons['products'][$category->getName()][] = $tgwa_addon->getSku();
                                    $this->tgwa_addons['products'][$tgwa_addon->getSku()] = $category->getExtraOptionType();
                                }
                            }
                        }
                    }
                default:
                    break;
            }
        }
        $this->_importResult = $this->_objectManager->create(Import\Result::class);

        // prepare all images data of media path
        $this->_rootDirectory = $this->_filesystem->getDirectoryRead(DirectoryList::ROOT);
        $mediaPath = $this->_rootDirectory->getAbsolutePath() . $this->_mediaPath;
        $this->_allImages = $this->_rootDirectory->readRecursively($mediaPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('catalog:product:import');
        $this->setDescription('Import Products.');
        $this->addArgument(
            self::INPUT_KEY_MEDIA_BEHAVIOR,
            InputArgument::OPTIONAL,
            'Type Media Behavior.'
        );

        $this->addArgument(
            self::INPUT_KEY_MEDIA_PATH,
            InputArgument::OPTIONAL,
            'Media Path.'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_canImportMedia = $input->getArgument(self::INPUT_KEY_MEDIA_BEHAVIOR) == 'with' ? true : false;
        $mediaPath = $input->getArgument(self::INPUT_KEY_MEDIA_PATH);
        if (trim($mediaPath)) {
            $this->_mediaPath = $mediaPath;
        }
        $output->setDecorated(true);
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
        }
        $this->_init();
        $output->writeln("Import Process Start :" . date('H:i:s'));
        try {
            $csvData = $this->getCSVData();
        } catch (\Exception $e) {
            $output->writeln("File Not found");
            $output->writeln("Import Finished." . date('H:i:s'));
            exit;
        }

        try {
            $output->writeln("Preparing products data :" . date('H:i:s'));
            if (count($csvData) > 1) {
                $this->checkAndCreateAttributes($csvData[0]);
                array_shift($csvData);
                $this->prepareProductData($csvData);
                $output->writeln("Prepared products data :" . date('H:i:s'));
                $output->writeln("Creating Import CSV :" . date('H:i:s'));
                //print_r($this->importData);exit;
                $source = $this->writeIntoCSV();
                $output->writeln("Created Import CSV :" . date('H:i:s'));
                $output->writeln("Validating product CSV :" . date('H:i:s'));
                $import = $this->getImport();
                $validationResult = $import->validateSource($source);
                $errorAggregator = $import->getErrorAggregator();

                if ($import->getProcessedRowsCount()) {
                    if ($validationResult && $this->getImport()->isImportAllowed()) {
                        $output->writeln("Start Importing :" . date('H:i:s'));
                        $import->importSource();
                        if ($import->getErrorAggregator()->hasToBeTerminated()) {
                            echo 'error';
                            exit;
                        }

                    } else {
                        $output->writeln("Data validation failed. Please fix the following errors and try to import again.");
                    }

                }

                if ($errorAggregator->getErrorsCount()) {
                    $counter = 0;
                    foreach ($this->getErrorMessages($errorAggregator) as $error) {
                        $message = ++$counter . '. ' . $error;
                        $output->writeln("Error: " . $message);
                        if ($counter >= self::LIMIT_ERRORS_MESSAGE) {
                            break;
                        }
                    }

                    foreach ($this->getSystemExceptions($errorAggregator) as $error) {
                        $output->writeln("Error: " . $error->getErrorMessage());
                        $output->writeln("Error additional data: " . $error->getErrorDescription());
                    }
                    if (!$validationResult && !$this->getImport()->isImportAllowed() && $import->getProcessedRowsCount() > 0 && $errorAggregator->getInvalidRowsCount() > 0) {
                        //$fileName = $this->_importResult->createErrorReport($errorAggregator);
                        $this->_importResult->createErrorReport($errorAggregator);
                    }
                }

                $output->writeln(sprintf('Checked rows: %s', $import->getProcessedRowsCount()));
                $output->writeln(sprintf('Checked entities: %s', $import->getProcessedEntitiesCount()));
                $output->writeln(sprintf('Invalid rows: %s', $errorAggregator->getInvalidRowsCount()));
                $output->writeln(sprintf('Total errors: %s', $errorAggregator->getErrorsCount()));
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            $output->writeln("Error: " . $e->getMessage());
        }
        $output->writeln("Import Finished." . date('H:i:s'));
        $output->writeln("Indexing Start." . date('H:i:s'));
        $this->reindexAll($output);

    }

    private function reindexAll($output)
    {
        foreach ($this->getAllIndexers() as $indexer) {
            $startTime = microtime(true);
            $indexer->reindexAll();
            $resultTime = microtime(true) - $startTime;
            $output->writeln(
                $indexer->getTitle() . ' index has been rebuilt successfully in ' . gmdate('H:i:s', $resultTime)
            );
        }
    }

    /**
     * Return the array of all indexers with keys as indexer ids.
     *
     * @return IndexerInterface[]
     */
    protected function getAllIndexers()
    {
        $indexers = $this->getCollectionFactory()->create()->getItems();
        return array_combine(
            array_map(
                function ($item) {
                    /** @var IndexerInterface $item */
                    return $item->getId();
                },
                $indexers
            ),
            $indexers
        );
    }

    /**
     * Get collection factory
     *
     * @return \Magento\Indexer\Model\Indexer\CollectionFactory
     * @deprecated 100.2.0
     */
    private function getCollectionFactory()
    {
        if (null === $this->indexCollectionFactory) {
            $this->indexCollectionFactory = $this->_objectManager
                ->get(\Magento\Indexer\Model\Indexer\CollectionFactory::class);
        }
        return $this->indexCollectionFactory;
    }

    private function writeIntoCSV()
    {
        $data = [
            'entity' => 'catalog_product',
            'behavior' => 'append',
            'validation_strategy' => 'validation-skip-errors',
            'allowed_error_count' => 10000,
            '_import_field_separator' => ',',
            '_import_multiple_value_separator' => ',',
            'fields_enclosure' => 1,
            'import_images_file_dir' => $this->_mediaPath
        ];

        $import = $this->getImport()->setData(
            $data
        );
        $fileName = $import->getWorkingDir() . $import->getEntity() . '.csv';
        $importData[] = array_keys($this->importData['simple'][0]);
        $importData = array_merge($importData, $this->importData['simple']);
        if (isset($this->importData['bundle'])) {
            $importData = array_merge($importData, $this->importData['bundle']);
        }

        if (isset($this->importData['configurable'])) {
            $importData = array_merge($importData, $this->importData['configurable']);
        }
        $this->csvProcessor->saveData($fileName, $importData);

        $source = ImportAdapter::findAdapterFor(
            $import->uploadSource(),
            $this->_objectManager->create(\Magento\Framework\Filesystem::class)
                ->getDirectoryWrite(DirectoryList::ROOT),
            $data[$import::FIELD_FIELD_SEPARATOR]
        );
        return $source;
    }

    /**
     * @return Import
     * @deprecated 100.1.0
     */
    private function getImport()
    {
        if (!$this->import) {
            $this->import = $this->_objectManager->get(Import::class);
        }
        return $this->import;
    }

    private function prepareProductData($data)
    {
        $skuKey = array_search('sku', $this->_columns);
        $configurableAttributeKey = array_search('configurable_attribute', $this->_columns);
        $designerKey = array_search('a_designer', $this->_columns);
        $parentProductKey = array_search('parent_product', $this->_columns);
        $curtainTypes = array_search('a_curtaintypes', $this->_columns);

        foreach ($data as $row => $itemData) {
            $this->importData['simple'][$row] = $this->_defaultFields;
            $canCreateBundle = $canCreateConfigurable = false;
            $curtainTypes = '';
            foreach ($itemData as $index => $value) {
                $field = $this->_columns[$index];
                switch ($field) {
                    case 'a_pattern_repeat_cm':
                        if(empty(trim($value))) {
                            $value = 1;
                        }
                        break;
                    case 'media':
                        if ($this->_canImportMedia) {
                            $images = explode(';', $value);
                            if (count($images) > 0) {
                                $mediaFields = $this->prepareMediaData($images, $itemData[$skuKey]);
                                $this->importData['simple'][$row] = array_merge($this->importData['simple'][$row], $mediaFields);
                            }
                        }
                        break;
                    case 'status':
                        $value = $value == 1 ? 1 : 2;
                        $this->importData['simple'][$row]['product_online'] = $value == 1 ? 1 : 0;
                        break;
                    case 'url_key':
                        $value = $this->prepareUrlKey($value, $itemData[$skuKey], $row);

                        /*if(empty($curtainTypes)) {
                            $this->_url_key[$value] = $itemData[$skuKey];
                        }*/
                        break;
                    case 'a_curtainname':
                        $this->importData['simple'][$row]['a_curtainname'] = $value;
                        $this->importData['simple'][$row]['a_curtainname_exist'] = '';
                        break;
                    case 'price':
                        $value = trim($value);
                        if ($value && is_numeric($value)) {
                            $value = $value;
                        }
                        break;
                    case 'a_curtaintypes':

                        if (!empty($value)) {
                            $canCreateBundle = true;
                            $curtainTypes = $value;
                        }
                        $this->importData['simple'][$row]['is_fabric_sewing'] = "Fabric";
                        break;
                    case 'categories':
                        if (
                            !empty(trim($itemData[$designerKey])) &&
                            !is_numeric(trim($itemData[$designerKey]))
                            && strlen(trim($itemData[$designerKey])) > 3
                        ) {
                            $designers = explode(',', $itemData[$designerKey]);
                            if (count($designers) > 1) {
                                foreach ($designers as $designer) {
                                    $designervalue = str_replace("/", "-", strtolower($designer));
                                    $value .= ";Default category/Designers/" . ucwords(trim(str_replace("-", " ", $designervalue)));
                                }
                            } else {
                                $designervalue = str_replace("/", "-", strtolower($itemData[$designerKey]));
                                $value .= ";Default category/Designers/" . ucwords(trim(str_replace("-", " ", $designervalue)));
                            }

                        }
                        $value = $this->checkAndCreateCategory($value);
                        break;
                    case 'qty':
                        if (!$value || $value <= 0) {
                            $this->importData['simple'][$row]['is_in_stock'] = 0;
                        }
                        $this->importData['simple'][$row]['use_config_backorders'] = 0;
                        $this->importData['simple'][$row]['allow_backorders'] = 1;
                        break;
                    case 'a_reversible':
                        $value = $value == 1 ? 'Yes' : 'No';
                        break;
                    case 'parent_product':
                        if (trim($value) && isset($itemData[$configurableAttributeKey]) && trim($itemData[$configurableAttributeKey])) {
                            $canCreateConfigurable = true;
                            $configurableProductName = trim($value);
                            $configurableAttributes = explode(',', $itemData[$configurableAttributeKey]);
                            $configurableSku = str_replace(' ', '-', $itemData[$parentProductKey]);
                        }
                        break;
                    case 'a_length_per_pcs':
                        $s = array('å', 'ä', 'ö', 'Å', 'Ä', 'Ö');
                        $r = array('a', 'a', 'o', 'a', 'a', 'o');
                        $value = str_replace($s, $r, $value);
                        if (strlen(trim($value)) >= 250) {
                            $value = str_replace($s, $r, substr($value, 0, 250));
                        }
                        break;
                }

                if(in_array($field, $this->_optionTypeAttributes)) {
                    switch ($field) {
                        case 'a_width':
                        case 'a_length':
                            if (empty(trim($value))) {
                                $value = 250;
                            }
                            break;
                        default:
                            if (trim($value)) {
                                $value = ucwords(strtolower($value));
                            } else {
                                $value = '';
                            }
                            break;
                    }
                    if (trim($value)) {                
                        $this->checkAndCreateAttributeOption($field, $value);
                    }
                }
                if (!in_array($field, $this->_excludeFields)) {
                    $this->importData['simple'][$row][$field] = $value;
                }

            }

            if (!in_array($this->importData['simple'][$row]['sku'], $this->_existingProductSkus)) {
                $todayDate = $this->_localeDate->date();
                $this->importData['simple'][$row]['news_from_date'] = $todayDate->format('Y-m-d H:i:s');
                $this->importData['simple'][$row]['news_to_date'] = $todayDate->add(new \DateInterval('P30D'))->format('Y-m-d H:i:s');
            }


            if ($canCreateBundle) {
                $this->prepareBundleProducts($this->importData['simple'][$row], $curtainTypes, $row);
            }

            if ($canCreateConfigurable) {
                $this->_configurableProductsData[$configurableProductName]['attributes'] = $configurableAttributes;
                $this->_configurableProductsData[$configurableProductName]['configurable_sku'] = $configurableSku;
                $this->_configurableProductsData[$configurableProductName]['associate_products'][] = $this->importData['simple'][$row];

            }
            //print_r($this->importData['simple'][$row]);exit;
            //break;
        }

        /*echo '<pre>';
        print_r($this->importData['simple']);
        exit;*/
        if (!empty($this->_configurableProductsData)) {
            $this->prepareConfigurableProducts();
        }

    }

    private function prepareConfigurableProducts()
    {
        foreach ($this->_configurableProductsData as $name => $data) {
            $minimumPrice = false;
            $rowData = [];
            $variation = [];
            $isInStock = false;
            foreach ($this->_configurableProductsData[$name]['associate_products'] as $product) {
                if ($minimumPrice === false || $minimumPrice > $product['price']) {
                    $minimumPrice = $product['price'];
                    $rowData = $product;
                }
                $variationData = [
                    'sku=' . $product['sku']
                ];
                foreach ($this->_configurableProductsData[$name]['attributes'] as $attributeCode) {
                    $attributeCode = strtolower($attributeCode);
                    if (isset($product[$attributeCode])) {
                        $variationData[] = $attributeCode . '=' . $product[$attributeCode];
                    }
                }
                $variation[] = implode(',', $variationData);

                if ($product['is_in_stock'] == 1) {
                    $isInStock = true;
                }
            }
            $urlKey = str_replace(' ', '-', $name);
            $url_key = $urlKey . '-c';
            $configurableData = array_replace($rowData, [
                'sku' => 'C-' . $this->_configurableProductsData[$name]['configurable_sku'],
                'name' => $name,
                'product_type' => Configurable::TYPE_CODE,
                'url_key' => $url_key,
                'configurable_variations' => implode('|', $variation),
                'is_in_stock' => $isInStock
            ]);

            foreach ($this->_configurableProductsData[$name]['attributes'] as $attributeCode) {
                $attributeCode = strtolower($attributeCode);
                $configurableData[$attributeCode] = '';
            }

            $this->importData['configurable'][] = $configurableData;
        }
    }

    private function prepareBundleProducts($rowData, $sewingSkus, $row = 0)
    {
        $sewingSkus = explode(';', $sewingSkus);
        if (count($sewingSkus) > 0) {
            $this->loadSewingProductData($sewingSkus);
            $sewingSkus = array_keys($this->_sewingProducts);

            if (count($sewingSkus)) {
                if (!empty(trim($rowData['a_curtainname']))) {
                    if (!in_array($rowData['a_curtainname'], $this->_curtainname)) {
                        $this->_curtainname[] = $rowData['a_curtainname'];
                    } else {
                        $rowData['a_curtainname_exist'] = $rowData['a_curtainname'] . '|' . $rowData['sku'];
                    }

                }
                foreach ($sewingSkus as $sku) {
                    $this->prepareRowDataOfBundle($rowData, $sku, $row);
                }
                unset($rowData['a_curtainname_exist']);
            }
        }
    }

    private function prepareRowDataOfBundle($rowData, $sewingSku, $row = 0)
    {
        //echo $sewingSku;
        $articleSku = '';
        if (!empty(trim($rowData['a_curtainname_exist']))) {
            $curtainArray = explode('|', $rowData['a_curtainname_exist']);
            if (count($curtainArray) > 1) {
                $articleSku = $curtainArray[1];
                $bundle_url_key = str_replace(' ', '-', trim($rowData['a_curtainname_exist']) . ' ' . $this->_bundleNamePostfix[$sewingSku] . ' ' . $articleSku);
            } else {
                $bundle_url_key = str_replace(' ', '-', trim($rowData['a_curtainname_exist']) . ' ' . $this->_bundleNamePostfix[$sewingSku]);

            }
        } else {
            $bundle_url_key = str_replace(' ', '-', $rowData['name'] . ' ' . $this->_bundleNamePostfix[$sewingSku]);
        }


        !empty(trim($rowData['a_curtainname'])) ? $bundle_name = trim($rowData['a_curtainname']) . ' ' . $this->_bundleNamePostfix[$sewingSku] : $bundle_name = $rowData['name'] . ' ' . $this->_bundleNamePostfix[$sewingSku];
        $bundle_url_key = strtolower($bundle_url_key);
        if ($bundled_product = $this->productFactory->create()->loadByAttribute('sku', $sewingSku . $rowData['sku'])) {
            $optionCollection = $bundled_product->getTypeInstance(true)->getOptionsCollection($bundled_product);
            $selectionCollection = $bundled_product
                ->getTypeInstance(true)
                ->getSelectionsCollection(
                    $bundled_product
                        ->getTypeInstance(true)
                        ->getOptionsIds($bundled_product),
                    $bundled_product
                );
            foreach ($optionCollection as $option) {
                $option->delete();
            }
        }


        $bundleData = array_replace($rowData, [
            'product_type' => ProductType::TYPE_BUNDLE,
            'bundle_sku_type' => 'fixed',
            'bundle_price_type' => 'dynamic',
            'bundle_price_view' => 'Price range',
            'bundle_weight_type' => 'dynamic',
            'shipment_type' => 'Together',
            'sku' => $sewingSku . $rowData['sku'],
            'name' => $bundle_name,
            'sewing_article_type' => $this->_bundleArticalType[$sewingSku],
            'bundle_values' => $this->getBundleValues($rowData['sku'], $sewingSku, $rowData['a_pattern_repeat_cm'],$rowData['a_width']),
            'custom_options' => $this->getCustomOptions($sewingSku),
            'url_key' => $this->prepareUrlKey($bundle_url_key, $sewingSku . str_replace('/', '_', $rowData['sku']), $row),
            'qty' => 0,
            'is_in_stock' => 1,
            'price' => '',
            'is_fabric_sewing' => '',
            'categories' => $this->_bundleCategoryPath[$sewingSku]
        ]);

        $mediaImage = [];
        if (trim($rowData['base_image'])) {
            $mediaImage[] = $rowData['base_image'];
            if (strlen(trim($rowData['additional_images'])) > 3) {
                $mediaImage = array_merge($mediaImage, explode(",", $rowData['additional_images']));
            }
        }
        $bundleData = array_replace($bundleData, $this->prepareMediaData($mediaImage, $bundleData['sku']));
        $this->importData['bundle'][] = $bundleData;
    }

    private function getCustomOptions($sewingSku)
    {
        $options = [
            sprintf($this->_customOptionString['field'], 'Antal', 0, 0.0000, 'fixed', '', 1, 1, 'bundleqty', 1, 3)
        ];
        switch ($sewingSku) {
            case 'TGSL':
                $options[] = sprintf($this->_customOptionString['field'], 'Höjd', 0, 0.0000, 'fixed', '', 1, 45, 'height', 1, 3);
                $options[] = sprintf($this->_customOptionString['field'], 'Bredd', 0, 0.0000, 'fixed', '', 1, 120, 'width', 1, 3);
                break;
            case 'TGWA':
                $options[] = sprintf($this->_customOptionString['field'], 'Längd', 0, 0.0000, 'fixed', '', 1, 250, 'length', 1, 3);
                $options[] = sprintf($this->_customOptionString['field'], 'Täckmått', 0, 0.0000, 'fixed', '', 1, 110, 'width', 1, 3);
                break;
            case 'TGVE':
                $options[] = sprintf($this->_customOptionString['field'], 'Höjd', 0, 0.0000, 'fixed', '', 1, 45, 'height', 1, 3);
                $options[] = sprintf($this->_customOptionString['field'], 'Täckmått', 0, 0.0000, 'fixed', '', 1, 120, 'width', 1, 3);
                foreach (['Ja', 'Nej'] as $optionValue) {
                    $options[] = sprintf($this->_customOptionString['select'], 'Vill du ha rynkhuvud?', 'radio', 1, 0.0000, 'fixed', '', 1, 'ja_nej', '', $optionValue);
                }
                break;
            case 'TGGL':
                $options[] = sprintf($this->_customOptionString['field'], 'Längd', 0, 0.0000, 'fixed', '', 1, 240, 'length', 1, 3);
                break;
            case 'TGOL':
                $options[] = sprintf($this->_customOptionString['field'], 'Längd', 0, 0.0000, 'fixed', '', 1, 240, 'length', 1, 3);
                break;
            case 'TGPA':
                $options[] = sprintf($this->_customOptionString['field'], 'Längd', 0, 0.0000, 'fixed', '', 1, 240, 'length', 1, 3);
                $options[] = sprintf($this->_customOptionString['field'], 'Bredd', 0, 0.0000, 'fixed', '', 1, 45, 'width', 1, 3);
                break;
            case 'TGHI':

                $options[] = sprintf($this->_customOptionString['field'], 'Längd', 0, 0.0000, 'fixed', '', 1, 165, 'length', 1, 3);
                $options[] = sprintf($this->_customOptionString['field'], 'Bredd', 0, 0.0000, 'fixed', '', 1, 120, 'width', 1, 3);
                foreach (['Vänster', 'Höger'] as $optionValue) {
                    $options[] = sprintf($this->_customOptionString['select'], 'Dragsida', 'radio', 1, 0.0000, 'fixed', '', 1, 'left_right', '', $optionValue);
                }
                break;
            default:
                break;
        }
        return implode('|', $options);
    }

    private function getBundleValues($articleSku, $sewingSku, $patternRepeat, $fabricWidth)
    {
        $values = [];
        $require = 0;
        switch ($sewingSku) {
            case 'TGGL':
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, 2.7000, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.0000, 'fixed', 1),
                ];

                if (!empty($this->tggl_addons)) {
                    if (!empty($this->tggl_addons['category'])) {
                        foreach ($this->tggl_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (!empty($this->tggl_addons['products'][$categoryName])) {
                                foreach ($this->tggl_addons['products'][$categoryName] as $extra) {

                                    $type = $this->tggl_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            case 'TGOL':
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, 2.7000, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.0000, 'fixed', 1),
                ];
                if (!empty($this->tgol_addons)) {
                    if (!empty($this->tgol_addons['category'])) {
                        foreach ($this->tgol_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (!empty($this->tgol_addons['products'][$categoryName])) {
                                foreach ($this->tgol_addons['products'][$categoryName] as $extra) {
                                    $type = $this->tgol_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            case 'TGSL':
                $fabricDefaultQty = ceil(75/$patternRepeat)*$patternRepeat/100;

                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, $fabricDefaultQty, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.0000, 'fixed', 1),
                ];
                if (!empty($this->tgsl_addons)) {
                    if (!empty($this->tgsl_addons['category'])) {
                        foreach ($this->tgsl_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (!empty($this->tgsl_addons['products'][$categoryName])) {
                                foreach ($this->tgsl_addons['products'][$categoryName] as $extra) {
                                    $type = $this->tgsl_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            case 'TGPA':
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, 2.6000, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.0000, 'fixed', 1),
                ];
                if (!empty($this->tgpa_addons)) {
                    if (!empty($this->tgpa_addons['category'])) {
                        foreach ($this->tgpa_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (!empty($this->tgpa_addons['products'][$categoryName])) {
                                foreach ($this->tgpa_addons['products'][$categoryName] as $extra) {
                                    $type = $this->tgpa_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            case 'TGHI':
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, 1.8500, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.0000, 'fixed', 1),
                ];
                if (!empty($this->tghi_addons)) {
                    if (!empty($this->tghi_addons['category'])) {
                        foreach ($this->tghi_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (!empty($this->tghi_addons['products'][$categoryName])) {
                                foreach ($this->tghi_addons['products'][$categoryName] as $extra) {
                                    $type = $this->tghi_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            case 'TGVE':
            if($patternRepeat > 1) { 
                $fabricDefaultQty = ((ceil(75/$patternRepeat)*$patternRepeat)/100)*ceil(203/$fabricWidth);
            }else{
                $fabricDefaultQty = 2.03; 
            }                  
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, $fabricDefaultQty, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.98, 'fixed', 1),
                ];
                if (!empty($this->tgve_addons)) {
                    if (!empty($this->tgve_addons['category'])) {
                        foreach ($this->tgve_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (!empty($this->tgve_addons['products'][$categoryName])) {
                                foreach ($this->tgve_addons['products'][$categoryName] as $extra) {
                                    $type = $this->tgve_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            case 'TGWA':
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, 2.3000, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 2.3, 'fixed', 1),
                ];
                if (!empty($this->tgwa_addons)) {
                    if (!empty($this->tgwa_addons['category'])) {
                        foreach ($this->tgwa_addons['category'] as $index => $categoryArray) {
                            $categoryName = $categoryArray[0];
                            $require = $categoryArray['is_option_required'];
                            if (isset($this->tgwa_addons['products'][$categoryName]) && sizeof($this->tgwa_addons['products'][$categoryName]) > 0) {
                                foreach ($this->tgwa_addons['products'][$categoryName] as $extra) {
                                    $type = $this->tgwa_addons['products'][$extra];
                                    $values[$extra] = sprintf($this->_bundleValueString, $categoryName, $type, $require, $extra, 12.0000, 0, 1.0000, 'fixed', 1);
                                }
                            }
                        }
                    }
                }
                break;
            default:
                $values = [
                    'fabric' => sprintf($this->_bundleValueString, 'Fabric', 'select', 1, $articleSku, 12.0000, 1, 2.0000, 'fixed', 1),
                    'sewing' => sprintf($this->_bundleValueString, 'Sewing', 'select', 1, $sewingSku, 0.0000, 1, 1.0000, 'fixed', 1),
                ];
                break;
        }
        return implode('|', $values);
    }

    private function loadSewingProductData(array $sewingSkus = [])
    {
        $loadedSkus = empty($this->_sewingProducts) ? [] : array_keys($this->_sewingProducts);
        if (!empty($loadedSkus)) {
            $skuToLoad = array_diff($sewingSkus, $loadedSkus);
        } else {
            $skuToLoad = $sewingSkus;
        }

        if (!empty($skuToLoad)) {
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect(['sku', 'name'])
                ->addAttributeToFilter('sku', ['in' => $skuToLoad]);

            if (!$collection->count()) {
                return false;
            }
            foreach ($collection as $product) {
                $this->_sewingProducts[$product->getSku()] = $product;
            }
        }
        return true;
    }

    private function checkArrayKeyExists($urlKey, $urlArray, $cnt = 2)
    {
        $newUrlKey = $urlKey . '-' . $cnt;
        if (array_key_exists($urlKey, $urlArray)) {
            $this->checkArrayKeyExists($newUrlKey, $urlArray, $cnt++);
        } else {
            return $newUrlKey;
        }
        return $newUrlKey;
    }

    private function prepareUrlKey($rowValue, $sku, $row = 0)
    {

        $s = array('å', 'ä', 'ö', 'Å', 'Ä', 'Ö');
        $r = array('a', 'a', 'o', 'a', 'a', 'o');

        if (in_array($sku, $this->urlRewrites)) {
            return array_search($sku, $this->urlRewrites);
        }
        $urlKey = strtolower($rowValue);
        $urlKey = str_replace($s, $r, $urlKey);

        if (array_key_exists($urlKey, $this->urlRewrites)) {
            $urlKey .= "-1";
            $this->urlRewrites[$urlKey] = $sku;
        }
        if (array_key_exists($urlKey, $this->_url_key)) {
            $urlKey .= "-" . $row;
        }
        $this->_url_key[$urlKey] = $sku;
        return $urlKey;
    }

    private function checkAndCreateCategory($categoryString)
    {
        $categories = explode(';', $categoryString);
        foreach ($categories as $key => $categoryPath) {
            if (empty(trim($categoryPath))) {
                unset($categories[$key]);
                continue;
            }
            $categoryNodes = explode('/', $categoryPath);
            foreach ($categoryNodes as $nKey => $category) {
                $categoryNodes[$nKey] = ucwords(trim(str_replace("-", " ", $category)));;
            }
            $categories[$key] = implode('/', array_filter($categoryNodes));
        }
        $categoryString = implode(',', array_filter($categories));

        $this->categoryProcessor->upsertCategories(
            $categoryString,
            ','
        );
        return $categoryString;
    }

    private function prepareMediaData($images, $sku)
    {
        $mediaFields = $mediaImages = [];

        //Existing image based on sku
        $existingImages = $this->_rootDirectory->search('{' . $sku . '*}', $this->_mediaPath);
        if (count($existingImages)) {
            foreach ($existingImages as $key => $imagePath) {
                $mediaImages[] = str_replace($this->_mediaPath, '', $imagePath);
            }
        }

        if (count($images) > 0) {
            foreach ($images as $image) {
                $image = strpos($image, "/") == 0 ? $image : "/" . $image;
                if (in_array($this->_mediaPath . $image, $this->_allImages)) {
                    $mediaImages[] = $image;
                }
            }
        }

        $mediaImages = array_values(array_unique($mediaImages));

        if (count($mediaImages)) {
            $mediaFields = [
                'base_image' => $mediaImages[0],
                'small_image' => $mediaImages[0],
                'thumbnail_image' => $mediaImages[0]
            ];

            array_shift($mediaImages);
            if (count($mediaImages) > 0) {
                $mediaFields['additional_images'] = implode(',', $mediaImages);
            }
        }
        return $mediaFields;
    }

    private function checkAndCreateAttributeOption($attributeCode, $value)
    {
        if (!isset($this->_loadedAttributeOptions[$attributeCode]) || !in_array($value, $this->_loadedAttributeOptions[$attributeCode])) {
            $option = new \Magento\Framework\DataObject();
            $option->setLabel($value);
            $option->setSortOrder(0);
            $option = $this->productAttributeOptionManagement->add($attributeCode, $option);
            $this->_loadedAttributeOptions[$attributeCode][] = $value;
        }
    }

    private function checkAndCreateAttributes($data)
    {
        foreach ($data as $key => $attributeCode) {
            $data[$key] = strtolower(str_replace('-', '_', str_replace('.', '_', $attributeCode)));
        }
        $this->_columns = $data;

        $attributeToSearch = array_diff($this->_columns, $this->_excludeAttributeToCreate);
        $attributeToSearch = array_diff($attributeToSearch, $this->_excludeFields);
        $productAttributeCollection = $this->attributeCollectionFactory->create();
        $productAttributes = $productAttributeCollection->getColumnValues('attribute_code');
        $newAttributes = array_diff($attributeToSearch, $productAttributes);
        $attributes = $productAttributeCollection->toArray([
            'attribute_code',
            'frontend_input',
            'source_model'
        ]);
        //print_r($attributes);exit;
        foreach($attributes['items'] as $attribute) {
            if($attribute['frontend_input'] == 'select' && $attribute['source_model'] == 'Magento\Eav\Model\Entity\Attribute\Source\Table') {
                $this->_optionTypeAttributes[] = $attribute['attribute_code'];
            }
        }
        if (!empty($newAttributes) && count($newAttributes) > 0) {
            $this->createAttributes($newAttributes);
        }
    }

    private function createAttributes($attributes)
    {
        foreach ($attributes as $attributeCode) {
            try {
                $eavSetup = $this->_eavSetupFactory->create();
                $eavSetup->addAttribute(
                    \Magento\Catalog\Model\Product::ENTITY,
                    $attributeCode,
                    [
                        'type' => 'varchar',
                        'backend' => '',
                        'frontend' => '',
                        'label' => str_replace('_', ' ', ucwords($attributeCode)),
                        'input' => 'text',
                        'class' => '',
                        'source' => '',
                        'global' => 1,
                        'visible' => true,
                        'required' => false,
                        'user_defined' => true,
                        'default' => '',
                        'searchable' => false,
                        'filterable' => false,
                        'comparable' => false,
                        'visible_on_front' => false,
                        'used_in_product_listing' => false,
                        'unique' => false,
                        'apply_to' => 'simple,grouped,bundle,configurable,virtual',
                        'system' => 1,
                        'group' => 'A_System',
                        'attribute_set' => 'Default']
                );
            } catch (\Exception $e) {
            }
        }
    }

    protected function getCSVData()
    {
        $path = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath() . 'Eastlane/';
        $filename = 'product_import.csv';
        return $this->csvProcessor->getData($path . $filename);
    }

    /**
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @return \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError[]
     */
    protected function getSystemExceptions(ProcessingErrorAggregatorInterface $errorAggregator)
    {
        return $errorAggregator->getErrorsByCode([AbstractEntity::ERROR_CODE_SYSTEM_EXCEPTION]);
    }

    /**
     * @param \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator
     * @return array
     */
    protected function getErrorMessages(ProcessingErrorAggregatorInterface $errorAggregator)
    {
        $messages = [];
        $rowMessages = $errorAggregator->getRowsGroupedByErrorCode([], [AbstractEntity::ERROR_CODE_SYSTEM_EXCEPTION]);
        foreach ($rowMessages as $errorCode => $rows) {
            $messages[] = $errorCode . ' ' . __('in row(s):') . ' ' . implode(', ', $rows);
        }
        return $messages;
    }
}
