<?php

namespace DeveloperHub\UrlRewritePro\Model;

use Exception;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Catalog\Model\ResourceModel\Product\ActionFactory as ProductActionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGeneratorFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGeneratorFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use DeveloperHub\UrlRewritePro\Helper\Regenerate as RegenerateHelper;

class ProductRewrite extends RegenerateRewrite
{
    /** @var string */
    protected $entityType = 'product';

    /** @var int */
    protected $productsCollectionPageSize = 1000;

    /** @var ProductActionFactory */
    protected $productActionFactory;

    /** @var Action */
    protected $productAction;

    /** @var ProductUrlRewriteGeneratorFactory */
    protected $productUrlRewriteGeneratorFactory;

    /** @var ProductUrlRewriteGenerator */
    protected $productUrlRewriteGenerator;

    /** @var ProductUrlPathGeneratorFactory */
    protected $productUrlPathGeneratorFactory;

    /** @var ProductUrlPathGenerator */
    protected $productUrlPathGenerator;

    /** @var ProductCollectionFactoryy */
    protected $productCollectionFactory;

    /**
     * RegenerateProductRewrites constructor.
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     * @param ProductActionFactory $productActionFactory
     * @param StoreManagerInterface $storeManager
     * @param ProductUrlRewriteGeneratorFactory $productUrlRewriteGeneratorFactory
     * @param ProductUrlPathGeneratorFactory $productUrlPathGeneratorFactory
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        RegenerateHelper $helper,
        ResourceConnection $resourceConnection,
        ProductActionFactory $productActionFactory,
        StoreManagerInterface $storeManager,
        ProductUrlRewriteGeneratorFactory $productUrlRewriteGeneratorFactory,
        ProductUrlPathGeneratorFactory $productUrlPathGeneratorFactory,
        ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($helper, $resourceConnection, $storeManager);

        $this->productActionFactory = $productActionFactory;
        $this->productUrlRewriteGeneratorFactory = $productUrlRewriteGeneratorFactory;
        $this->productUrlPathGeneratorFactory = $productUrlPathGeneratorFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Regenerate Products Url Rewrites in specific store
     * @return $this
     */
    public function regenerate($storeId = 0)
    {
        if (count($this->regenerateOptions['productsFilter']) > 0) {
            $this->regenerateProductsRangeUrlRewrites(
                $this->regenerateOptions['productsFilter'],
                $storeId
            );
        } elseif (!empty($this->regenerateOptions['productId'])) {
            $this->regenerateSpecificProductUrlRewrites(
                $this->regenerateOptions['productId'],
                $storeId
            );
        } else {
            $this->regenerateAllProductsUrlRewrites($storeId);
        }

        return $this;
    }

    /**
     * Regenerate all products Url Rewrites
     * @param int $storeId
     * @return $this
     */
    public function regenerateAllProductsUrlRewrites($storeId = 0)
    {
        $this->regenerateProductsRangeUrlRewrites([], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites for a specific product
     * @param int $productId
     * @param int $storeId
     * @return $this
     */
    public function regenerateSpecificProductUrlRewrites($productId, $storeId = 0)
    {
        $this->regenerateProductsRangeUrlRewrites([$productId], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites for a products range
     * @param array $productsFilter
     * @param int $storeId
     * @return $this
     */
    public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0)
    {
        $products = $this->getProductsCollection($productsFilter, $storeId);
        $pageCount = $products->getLastPageNumber();
        $this->progressBarProgress = 1;
        $this->progressBarTotal = (int)$products->getSize();
        $currentPage = 1;

        while ($currentPage <= $pageCount) {
            $products->clear();
            $products->setCurPage($currentPage);

            foreach ($products as $product) {
                $this->processProduct($product, $storeId);
            }

            $currentPage++;
        }

        $this->updateSecondaryTable();

        return $this;
    }

    /**
     * Regenerate Url Rewrites for specific product in specific store
     * @param $entity
     * @param $storeId
     * @return $this
     * @throws Exception
     */
    public function processProduct($entity, $storeId = 0)
    {
        $entity->setStoreId($storeId)->setData('url_path', null);

        if ($this->regenerateOptions['saveOldUrls']) {
            $entity->setData('save_rewrites_history', true);
        }
        $updateAttributes = ['url_path' => null];
        if (!$this->regenerateOptions['noRegenUrlKey']) {
            $generatedKey = $this->getProductUrlPathGenerator()->getUrlKey($entity->setUrlKey(null));
            $updateAttributes['url_key'] = $generatedKey;
        }

        $this->getProductAction()->updateAttributes(
            [$entity->getId()],
            $updateAttributes,
            $storeId
        );

        $urlRewrites = $this->getProductUrlRewriteGenerator()->generate($entity);
        $urlRewrites = $this->helper->sanitizeProductUrlRewrites($urlRewrites);

        if (!empty($urlRewrites)) {
            $this->saveUrlRewrites(
                $urlRewrites,
                [['entity_type' => $this->entityType, 'entity_id' => $entity->getId(), 'store_id' => $storeId]]
            );
        }

        $this->progressBarProgress++;

        return $this;
    }

    /** @return Action */
    protected function getProductAction()
    {
        if (is_null($this->productAction)) {
            $this->productAction = $this->productActionFactory->create();
        }

        return $this->productAction;
    }

    /**
     * @return ProductUrlRewriteGenerator
     */
    protected function getProductUrlRewriteGenerator()
    {
        if (is_null($this->productUrlRewriteGenerator)) {
            $this->productUrlRewriteGenerator = $this->productUrlRewriteGeneratorFactory->create();
        }

        return $this->productUrlRewriteGenerator;
    }

    /**
     * @return ProductUrlPathGenerator
     */
    protected function getProductUrlPathGenerator()
    {
        if (is_null($this->productUrlPathGenerator)) {
            $this->productUrlPathGenerator = $this->productUrlPathGeneratorFactory->create();
        }

        return $this->productUrlPathGenerator;
    }

    /**
     * Get products collection
     * @param array $productsFilter
     * @param int $storeId
     * @return mixed
     */
    protected function getProductsCollection($productsFilter = [], $storeId = 0)
    {
        $productsCollection = $this->productCollectionFactory->create();

        $productsCollection->setStore($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            ->setPageSize($this->productsCollectionPageSize);
        if (count($productsFilter) > 0) {
            $productsCollection->addIdFilter($productsFilter);
        }
        return $productsCollection;
    }
}
