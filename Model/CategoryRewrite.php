<?php

namespace DeveloperHub\UrlRewritePro\Model;

use Exception;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandlerFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use DeveloperHub\UrlRewritePro\Helper\Regenerate as RegenerateHelper;

class CategoryRewrite extends RegenerateRewrite
{
    /** @var string */
    protected $entityType = 'category';

    /**@var int */
    protected $categoriesCollectionPageSize = 100;

    /** @var array */
    protected $dataUrlRewriteClassNames = [];

    /**@var DatabaseMapPool */
    protected $databaseMapPool;

    /**@var CategoryCollectionFactory */
    protected $categoryCollectionFactory;

    /**@var CategoryUrlPathGeneratorFactory */
    protected $categoryUrlPathGeneratorFactory;

    /** @var CategoryUrlPathGenerator */
    protected $categoryUrlPathGenerator;

    /** @var CategoryUrlRewriteGeneratorFactory */
    protected $categoryUrlRewriteGeneratorFactory;

    /** @var CategoryUrlRewriteGenerator */
    protected $categoryUrlRewriteGenerator;

    /** @var UrlRewriteHandlerFactory */
    protected $urlRewriteHandlerFactory;

    /** @var UrlRewriteHandler */
    protected $urlRewriteHandler;

    /** @var RegenerateProductRewrites */
    protected $regenerateProductRewrites;

    /**
     * RegenerateCategoryRewrites constructor.
     * @param RegenerateHelper $helper
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param DatabaseMapPool $databaseMapPool
     * @param CategoryUrlPathGeneratorFactory $categoryUrlPathGeneratorFactory
     * @param CategoryUrlRewriteGeneratorFactory $categoryUrlRewriteGeneratorFactory
     * @param UrlRewriteHandlerFactory $urlRewriteHandlerFactory
     * @param ProductRewrite $regenerateProductRewrites
     */
    public function __construct(
        RegenerateHelper $helper,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        CategoryCollectionFactory $categoryCollectionFactory,
        DatabaseMapPool $databaseMapPool,
        CategoryUrlPathGeneratorFactory $categoryUrlPathGeneratorFactory,
        CategoryUrlRewriteGeneratorFactory $categoryUrlRewriteGeneratorFactory,
        UrlRewriteHandlerFactory $urlRewriteHandlerFactory,
        ProductRewrite $regenerateProductRewrites
    ) {
        parent::__construct($helper, $resourceConnection, $storeManager);

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->databaseMapPool = $databaseMapPool;
        $this->categoryUrlPathGeneratorFactory = $categoryUrlPathGeneratorFactory;
        $this->categoryUrlRewriteGeneratorFactory = $categoryUrlRewriteGeneratorFactory;
        $this->urlRewriteHandlerFactory = $urlRewriteHandlerFactory;
        $this->regenerateProductRewrites = $regenerateProductRewrites;

        $this->dataUrlRewriteClassNames = [
            DataCategoryUrlRewriteDatabaseMap::class,
            DataProductUrlRewriteDatabaseMap::class
        ];
    }

    /**
     * Regenerate Categories and childs (sub-categories and related products) Url Rewrites  in specific store
     * @return $this
     */
    public function regenerate($storeId = 0)
    {
        if (count($this->regenerateOptions['categoriesFilter']) > 0) {
            $this->regenerateCategoriesRangeUrlRewrites(
                $this->regenerateOptions['categoriesFilter'],
                $storeId
            );
        } elseif (!empty($this->regenerateOptions['categoryId'])) {
            $this->regenerateSpecificCategoryUrlRewrites(
                $this->regenerateOptions['categoryId'],
                $storeId
            );
        } else {
            $this->regenerateAllCategoriesUrlRewrites($storeId);
        }
        return $this;
    }

    /**
     * Regenerate Url Rewrites of all categories
     * @param int $storeId
     * @return $this
     */
    public function regenerateAllCategoriesUrlRewrites($storeId = 0)
    {
        $this->regenerateCategoriesRangeUrlRewrites([], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites of specific category
     * @param int $categoryId
     * @param int $storeId
     * @return $this
     */
    public function regenerateSpecificCategoryUrlRewrites($categoryId, $storeId = 0)
    {
        $this->regenerateCategoriesRangeUrlRewrites([$categoryId], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites of a categories range
     * @param $categoriesFilter
     * @param $storeId
     * @return $this
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function regenerateCategoriesRangeUrlRewrites($categoriesFilter = [], $storeId = 0)
    {
        $categories = $this->getCategoriesCollection($categoriesFilter, $storeId);

        $pageCount = $categories->getLastPageNumber();
        $this->progressBarProgress = 0;
        $this->progressBarTotal = (int)$categories->getSize();
        $currentPage = 1;
        while ($currentPage <= $pageCount) {
            $categories->clear();
            $categories->setCurPage($currentPage);

            foreach ($categories as $category) {
                $this->categoryProcess($category, $storeId);
            }

            $currentPage++;
        }

        $this->updateSecondaryTable();

        return $this;
    }

    /**
     * Process category Url Rewrites re-generation
     * @param $category
     * @param $storeId
     * @return $this
     * @throws Exception
     */
    protected function categoryProcess($category, $storeId = 0)
    {
        $category->setStoreId($storeId);

        if ($this->regenerateOptions['saveOldUrls']) {
            $category->setData('save_rewrites_history', true);
        }

        if (!$this->regenerateOptions['noRegenUrlKey']) {
            $category->setOrigData('url_key', null);
            $category->setUrlKey($this->getCategoryUrlPathGenerator()->getUrlKey($category->setUrlKey(null)));
            $category->getResource()->saveAttribute($category, 'url_key');
        }

        $category->unsUrlPath();
        $category->setUrlPath($this->getCategoryUrlPathGenerator()->getUrlPath($category));
        $category->getResource()->saveAttribute($category, 'url_path');

        $category->setChangedProductIds(true);
        $categoryUrlRewriteResult = $this->getCategoryUrlRewriteGenerator()->generate($category, true);
        if (!empty($categoryUrlRewriteResult)) {
            $this->saveUrlRewrites($categoryUrlRewriteResult);
        }
        if ($this->helper->useCategoriesPathForProductUrls($storeId)) {
            $productsIds = $this->getCategoriesProductsIds($category->getAllChildren());
            if (!empty($productsIds)) {
                $this->regenerateProductRewrites->regenerateOptions = $this->regenerateOptions;
                $this->regenerateProductRewrites->regenerateOptions['showProgress'] = false;
                $this->regenerateProductRewrites->regenerateProductsRangeUrlRewrites($productsIds, $storeId);
            }
        }
        $this->resetUrlRewritesDataMaps($category);
        $this->progressBarProgress++;
        return $this;
    }

    /**
     * Get categories collection
     * @param $categoriesFilter
     * @param $storeId
     * @return Collection
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getCategoriesCollection($categoriesFilter = [], $storeId = 0)
    {
        $categoriesCollection = $this->categoryCollectionFactory->create();
        $categoriesCollection->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            ->addFieldToFilter('level', (count($categoriesFilter) > 0 ? ['gt' => '1'] : 2))
            ->setOrder('level', 'ASC')
            ->setPageSize($this->categoriesCollectionPageSize);
        $rootCategoryId = $this->getStoreRootCategoryId($storeId);
        if ($rootCategoryId > 0) {
            $categoriesCollection->addAttributeToFilter('path', ['like' => "1/{$rootCategoryId}/%"]);
        }
        if (count($categoriesFilter) > 0) {
            $categoriesCollection->addIdFilter($categoriesFilter);
        }
        return $categoriesCollection;
    }

    /**
     * Get products Ids which are related to specific categories
     * @param string $categoryIds
     * @return array
     */
    protected function getCategoriesProductsIds($categoryIds = '')
    {
        $result = [];

        if (!empty($categoryIds)) {
            $select = $this->getResourceConnection()->getConnection()->select()
                ->from($this->getCategoryProductsTableName(), ['product_id'])
                ->where("category_id IN ({$categoryIds})");
            $rows =  $this->getResourceConnection()->getConnection()->fetchAll($select);

            foreach ($rows as $row) {
                $result[] = $row['product_id'];
            }
        }

        return $result;
    }

    /**
     * Get category Url Path generator
     * @return mixed
     */
    protected function getCategoryUrlPathGenerator()
    {
        if (is_null($this->categoryUrlPathGenerator)) {
            $this->categoryUrlPathGenerator = $this->categoryUrlPathGeneratorFactory->create();
        }

        return $this->categoryUrlPathGenerator;
    }

    /**
     * Get category Url Rewrite generator
     * @return mixed
     */
    protected function getCategoryUrlRewriteGenerator()
    {
        if (is_null($this->categoryUrlRewriteGenerator)) {
            $this->categoryUrlRewriteGenerator = $this->categoryUrlRewriteGeneratorFactory->create();
        }

        return $this->categoryUrlRewriteGenerator;
    }

    /**
     * Get Url Rewrite handler
     * @return mixed
     */
    protected function getUrlRewriteHandler()
    {
        if (is_null($this->urlRewriteHandler)) {
            $this->urlRewriteHandler = $this->urlRewriteHandlerFactory->create();
        }
        return $this->urlRewriteHandler;
    }

    /**
     * Resets used data maps to free up memory and temporary tables
     *
     * @param $category
     * @return void
     */
    protected function resetUrlRewritesDataMaps($category)
    {
        foreach ($this->dataUrlRewriteClassNames as $className) {
            $this->databaseMapPool->resetMap($className, $category->getEntityId());
        }
    }
}
