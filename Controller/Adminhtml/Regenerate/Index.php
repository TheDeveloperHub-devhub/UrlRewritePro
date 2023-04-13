<?php

namespace DeveloperHub\UrlRewritePro\Controller\Adminhtml\Regenerate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use DeveloperHub\UrlRewritePro\Model\CategoryRewrite;
use DeveloperHub\UrlRewritePro\Model\ProductRewrite;

class Index extends Action
{
    /**@var RequestInterface */
    private $request;

    /**@var ProductUrlRewriteGenerator */
    protected $productUrlRewriteGenerator;

    /** @var \Magento\Framework\App\ResourceConnection */
    private $_resource;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $_storeManager;

    /** @var ProductRewrite */
    private $regenerateProductRewrites;

    /** @var CategoryRewrite */
    private $regenerateCategoryRewrites;

    /**
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     * @param CategoryRewrite $regenerateCategoryRewrites
     * @param ProductRewrite $regenerateProductRewrites
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        RequestInterface $request,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        CategoryRewrite $regenerateCategoryRewrites,
        ProductRewrite $regenerateProductRewrites
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->_resource = $resource;
        $this->_storeManager = $storeManager;
        $this->regenerateCategoryRewrites = $regenerateCategoryRewrites;
        $this->regenerateProductRewrites = $regenerateProductRewrites;
        $this->resultFactory = $resultFactory;
    }

    /** @return ResponseInterface|ResultInterface */
    public function execute()
    {
        $params = $this->request->getParams();
        if (count($params) < 2 || !isset($params['service_type'])) {
            return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        } else {
            //for store url rewrite
            if ($params['service_type'] == 0) {
                if ($params['store_id'] == 0) {
                    $allStoreid = $this->getAllStoreIds();
                    for ($i = 1;$i<count($allStoreid);$i++) {
                        $this->_storeManager->setCurrentStore($i);
                        $this->regenerateProductRewrites->regenerate($i);
                    }
                    $page =  $this->resultFactory->create(ResultFactory::TYPE_PAGE);
                    $this->messageManager->addSuccessMessage(__('Urls of stores rewrited'));
                } else {
                    $this->_storeManager->setCurrentStore($params['store_id']);
                    $this->regenerateProductRewrites->regenerate($params['store_id']);
                    $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
                    $this->messageManager->addSuccessMessage(__('Urls of store rewrited'));
                }
            }
            //for category Url Rewrite
            if ($params['service_type'] == 1) {
                if ($params['store_id']==0) {
                    $this->regenerateCategoryRewrites->regenerateAllCategoriesUrlRewrites(0);
                }
                for ($j = 0; $j<count($params['category_id']); $j++) {
                    $this->regenerateCategoryRewrites->regenerateSpecificCategoryUrlRewrites($params['category_id'][$j], $params['store_id']);
                    $page =  $this->resultFactory->create(ResultFactory::TYPE_PAGE);
                }
                $this->messageManager->addSuccessMessage(__('Categories Urls rewrited'));
            }
            //for products Url ReWrite
            if ($params['service_type'] == 2) {
                if ($params['store_id'] ==0) {
                    $this->regenerateProductRewrites->regenerateAllProductsUrlRewrites(0);
                }
                for ($i = 0; $i<count($params['products']); $i++) {
                    $this->regenerateProductRewrites->regenerateSpecificProductUrlRewrites($params['products'][$i]['entity_id'], $params['store_id']);
                    $page =  $this->resultFactory->create(ResultFactory::TYPE_PAGE);
                }
                $this->messageManager->addSuccessMessage(__('Products Urls Rewrited'));
            }
            return $page;
        }
    }
    protected function getAllStoreIds(): array
    {
        $result = [];
        $sql = $this->_resource->getConnection()->select()
            ->from($this->_resource->getTableName('store'), ['store_id', 'code', 'is_active'])
            ->order('store_id', 'ASC');

        $queryResult = $this->_resource->getConnection()->fetchAll($sql);

        foreach ($queryResult as $row) {
            $result[(int)$row['store_id']] = $row['code'];
        }

        return $result;
    }
}
