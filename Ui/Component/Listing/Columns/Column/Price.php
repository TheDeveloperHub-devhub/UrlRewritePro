<?php

namespace DeveloperHub\UrlRewritePro\Ui\Component\Listing\Columns\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use DeveloperHub\UrlRewritePro\Model\Service\ProductGrid\Price as PriceModifier;
use Zend_Currency_Exception;

class Price extends Column
{
    /** @var PriceModifier */
    private $priceModifier;

    /**
     * Price constructor.
     *
     * @param ContextInterface   $context
     * @param UiComponentFactory $uiComponentFactory
     * @param PriceModifier      $priceModifier
     * @param array              $components
     * @param array              $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        PriceModifier $priceModifier,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->priceModifier = $priceModifier;
    }

    /**
     * Prepare Data Source
     * @param array $dataSource
     * @return array
     * @throws Zend_Currency_Exception
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['price'])) {
                    $item['price'] = $this->priceModifier->toDefaultCurrency($item['price']);
                }
            }
        }

        return $dataSource;
    }
}
