<?php
namespace Eastlane\Productimport\Model\Storage;

use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\UrlRewrite\Model\OptionProvider;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory;
use Psr\Log\LoggerInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite as UrlRewriteData;
use Magento\Framework\ObjectManagerInterface;

class DbStorage extends \Magento\UrlRewrite\Model\Storage\DbStorage
{
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;


    public function __construct(
        UrlRewriteFactory $urlRewriteFactory,
        DataObjectHelper $dataObjectHelper,
        ResourceConnection $resource,
        LoggerInterface $logger,
        ObjectManagerInterface $objectManagerInterface)
    {
        parent::__construct($urlRewriteFactory, $dataObjectHelper, $resource, $logger);
        $this->objectManager = $objectManagerInterface;
        $this->connection = $resource->getConnection();
    }

    /**
     * {@inheritdoc}
     */
    protected function doReplace(array $urls)
    {
        foreach ($this->createFilterDataBasedOnUrls($urls) as $type => $urlData) {
            $urlData[UrlRewrite::ENTITY_TYPE] = $type;
            $this->deleteByData($urlData);
        }
       // $storeId_requestPaths = [];
        $data = [];
        foreach ($urls as $url) {
            $storeId = $url->getStoreId();
            $requestPath = $url->getRequestPath();
            $urlCollection = $this->objectManager->create(\Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection::class);
            $connection = $urlCollection;
            $connection->addFieldToFilter('store_id',$storeId)
                ->addFieldToFilter('request_path',$requestPath);
            $select = $connection->getSelect();
            $exists = $this->connection->fetchOne($select);

            if ($exists)
            {
                $urlModel = $this->objectManager->create(\Magento\UrlRewrite\Model\UrlRewrite::class);
                $urlModel->load($exists);
                $urlModel->delete();
            }
          //  if ($exists) continue;

          //  $storeId_requestPaths[] = $storeId . '-' . $requestPath;
            $data[] = $url->toArray();
        }

        try {
            $this->insertMultiple($data);
        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
            /** @var \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urlConflicted */
            $urlConflicted = [];
            foreach ($urls as $url) {
                $urlFound = $this->doFindOneByData(
                    [
                        UrlRewriteData::REQUEST_PATH => $url->getRequestPath(),
                        UrlRewriteData::STORE_ID => $url->getStoreId()
                    ]
                );
                if (isset($urlFound[UrlRewriteData::URL_REWRITE_ID])) {
                    $urlConflicted[$urlFound[UrlRewriteData::URL_REWRITE_ID]] = $url->toArray();
                }
            }
            if ($urlConflicted) {
                throw new \Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException(
                    __('URL key for specified store already exists storage -1.'.$url->getRequestPath()),
                    $e,
                    $e->getCode(),
                    $urlConflicted
                );
            } else {
                throw $e->getPrevious() ?: $e;
            }
        }

        return $urls;
    }
}
