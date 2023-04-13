<?php

namespace DeveloperHub\UrlRewritePro\Model;

use Exception;
use Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product as ProductUrlRewriteResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\Storage\DbStorage;
use DeveloperHub\UrlRewritePro\Helper\Regenerate as RegenerateHelper;

abstract class RegenerateRewrite
{
    /**@var string */
    protected $entityType = 'product';

    /** @var array */
    protected $storeRootCategoryId = [];

    /** @var integer */
    protected $progressBarProgress = 0;

    /** @var integer */
    protected $progressBarTotal = 0;

    /** @var string */
    protected $mainDbTable;

    /** @var string */
    protected $secondaryDbTable;

    /** @var string */
    protected $categoryProductsDbTable;

    /**Regenerate Rewrites custom options
     * @var array */
    public $regenerateOptions = [];

    /** @var RegenerateHelper */
    protected $helper;

    /** @var ResourceConnection */
    protected $resourceConnection;

    /** @var StoreManagerInterface  */
    private $storeManager;

    /**
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        RegenerateHelper $helper,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager
    ) {
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;

        $this->regenerateOptions = [
            'saveOldUrls' => false,
            'categoriesFilter' => [],
            'productsFilter' => [],
            'categoryId' => null,
            'productId' => null,
            'checkUseCategoryInProductUrl' => false,
            'noRegenUrlKey' => false
        ];
    }

    /**
     * Regenerate Url Rewrites in specific store
     * @param int $storeId
     * @return mixed
     */
    abstract public function regenerate($storeId = 0);

    /**
     * Return resource connection
     * @return ResourceConnection
     */
    protected function getResourceConnection()
    {
        return $this->resourceConnection;
    }

    /**
     * Save Url Rewrites
     * @param $urlRewrites
     * @param $entityData
     * @return $this
     * @throws Exception
     */
    public function saveUrlRewrites($urlRewrites, $entityData = [])
    {
        $data = $this->prepareUrlRewrites($urlRewrites);
        if (!$this->regenerateOptions['saveOldUrls']) {
            if (empty($entityData) && !empty($data)) {
                $entityData = $data;
            }
            $this->deleteCurrentRewrites($entityData);
        }
        $this->getResourceConnection()->getConnection()->beginTransaction();
        try {
            $this->getResourceConnection()->getConnection()->insertOnDuplicate(
                $this->getMainTableName(),
                $data,
                ['request_path', 'metadata']
            );
            $this->getResourceConnection()->getConnection()->commit();
        } catch (Exception $e) {
            $this->getResourceConnection()->getConnection()->rollBack();
            throw $e;
        }
        return $this;
    }

    /** @return string */
    protected function getMainTableName()
    {
        if (empty($this->mainDbTable)) {
            $this->mainDbTable = $this->getResourceConnection()->getTableName(DbStorage::TABLE_NAME);
        }
        return $this->mainDbTable;
    }

    /** @return string */
    protected function getSecondaryTableName()
    {
        if (empty($this->secondaryDbTable)) {
            $this->secondaryDbTable = $this->getResourceConnection()->getTableName(ProductUrlRewriteResource::TABLE_NAME);
        }
        return $this->secondaryDbTable;
    }

    /** @return string */
    protected function getCategoryProductsTableName()
    {
        if (empty($this->categoryProductsDbTable)) {
            $this->categoryProductsDbTable = $this->getResourceConnection()->getTableName('catalog_category_product');
        }
        return $this->categoryProductsDbTable;
    }

    /**
     * Delete current Url Rewrites
     * @param $entitiesData
     * @return $this
     * @throws Exception
     */
    protected function deleteCurrentRewrites($entitiesData = [])
    {
        if (!empty($entitiesData)) {
            $whereConditions = [];
            foreach ($entitiesData as $entityData) {
                $whereConditions[] = sprintf(
                    '(entity_type = \'%s\' AND entity_id = %d AND store_id = %d)',
                    $entityData['entity_type'],
                    $entityData['entity_id'],
                    $entityData['store_id']
                );
            }
            $whereConditions = array_unique($whereConditions);
            $this->getResourceConnection()->getConnection()->beginTransaction();
            try {
                $this->getResourceConnection()->getConnection()->delete(
                    $this->getMainTableName(),
                    implode(' OR ', $whereConditions)
                );
                $this->getResourceConnection()->getConnection()->commit();
            } catch (Exception $e) {
                $this->getResourceConnection()->getConnection()->rollBack();
                throw $e;
            }
        }
        return $this;
    }

    /**
     * Update "catalog_url_rewrite_product_category" table
     * @return $this
     */
    protected function updateSecondaryTable()
    {
        $this->getResourceConnection()->getConnection()->beginTransaction();
        try {
            $this->getResourceConnection()->getConnection()->delete(
                $this->getSecondaryTableName(),
                "url_rewrite_id NOT IN (SELECT url_rewrite_id FROM {$this->getMainTableName()})"
            );
            $this->getResourceConnection()->getConnection()->commit();
        } catch (Exception $e) {
            $this->getResourceConnection()->getConnection()->rollBack();
        }
        $select = $this->getResourceConnection()->getConnection()->select()
            ->from(
                $this->getMainTableName(),
                [
                    'url_rewrite_id',
                    'category_id' => new \Zend_Db_Expr(
                        'SUBSTRING_INDEX(SUBSTRING_INDEX(' . $this->getMainTableName() . '.metadata, \'"\', -2), \'"\', 1)'
                    ),
                    'product_id' =>'entity_id'
                ]
            )
            ->where('metadata LIKE \'{"category_id":"%"}\'')
            ->where("url_rewrite_id NOT IN (SELECT url_rewrite_id FROM {$this->getSecondaryTableName()})");
        $data = $this->getResourceConnection()->getConnection()->fetchAll($select);
        if (!empty($data)) {
            foreach ($data as $row) {
                $this->getResourceConnection()->getConnection()->beginTransaction();
                try {
                    $this->getResourceConnection()->getConnection()->insertOnDuplicate(
                        $this->getSecondaryTableName(),
                        $row,
                        ['product_id']
                    );
                    $this->getResourceConnection()->getConnection()->commit();
                } catch (Exception $e) {
                    $this->getResourceConnection()->getConnection()->rollBack();
                }
            }
        }
        return $this;
    }

    /**
     * @param array $urlRewrites
     * @return array
     */
    protected function prepareUrlRewrites($urlRewrites)
    {
        $result = [];
        foreach ($urlRewrites as $urlRewrite) {
            $rewrite = $urlRewrite->toArray();
            $originalRequestPath = trim($rewrite['request_path']);
            if (empty($originalRequestPath)) {
                continue;
            }
            $pathParts = pathinfo($originalRequestPath);
            $pathParts['dirname'] = trim($pathParts['dirname'], './');
            $pathParts['filename'] = trim($pathParts['filename'], './');
            $urlSuffix = substr($originalRequestPath, -1) === '/' ? '/' : '';
            $rewrite['request_path'] = $this->mergePartsIntoRewriteRequest($pathParts, '', $urlSuffix);
            $index = 0;
            while ($this->urlRewriteExists($rewrite)) {
                $index++;
                $rewrite['request_path'] = $this->mergePartsIntoRewriteRequest($pathParts, (string)$index, $urlSuffix);
            }

            $result[] = $rewrite;
        }

        return $result;
    }

    /**
     * Check if Url Rewrite with same request path exists
     * @param array $rewrite
     * @return bool
     */
    protected function urlRewriteExists($rewrite)
    {
        $select = $this->getResourceConnection()->getConnection()->select()
            ->from($this->getMainTableName(), ['url_rewrite_id'])
            ->where('entity_type = ?', $rewrite['entity_type'])
            ->where('request_path = ?', $rewrite['request_path'])
            ->where('store_id = ?', $rewrite['store_id'])
            ->where('entity_id != ?', $rewrite['entity_id']);
        return $this->getResourceConnection()->getConnection()->fetchOne($select);
    }

    /**
     * Merge Url Rewrite parts into one string
     * @param $pathParts
     * @param string $index
     * @param string $urlSuffix
     * @return string
     */
    protected function mergePartsIntoRewriteRequest($pathParts, $index = '', $urlSuffix = '')
    {
        return (!empty($pathParts['dirname']) ? $pathParts['dirname'] . '/' : '') . $pathParts['filename']
            . (!empty($index) ? '-' . $index : '')
            . (!empty($pathParts['extension']) ? '.' . $pathParts['extension'] : '')
            . ($urlSuffix ?: '');
    }

    /**
     * Get root category Id of specific store
     * @param $storeId
     * @return null
     * @throws NoSuchEntityException
     */
    protected function getStoreRootCategoryId($storeId)
    {
        if (empty($this->storeRootCategoryId[$storeId])) {
            $this->storeRootCategoryId[$storeId] = null;
            $store = $this->storeManager->getStore($storeId);
            if ($store) {
                $this->storeRootCategoryId[$storeId] = $store->getRootCategoryId();
            }
        }

        return $this->storeRootCategoryId[$storeId];
    }
}
