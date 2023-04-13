<?php

namespace DeveloperHub\UrlRewritePro\Model\Service\ProductGrid;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Currency_Exception;

class Price
{
    /** @var CurrencyInterface */
    private $currency;

    /**
     * @param CurrencyInterface $localeCurrency
     * @param StoreManagerInterface $storeManager
     * @throws NoSuchEntityException
     */
    public function __construct(CurrencyInterface $localeCurrency, StoreManagerInterface $storeManager)
    {
        $this->currency = $localeCurrency->getCurrency(
            $storeManager->getStore()->getBaseCurrencyCode()
        );
    }

    /**
     * @param $price
     * @return string
     * @throws Zend_Currency_Exception
     */
    public function toDefaultCurrency($price = 0)
    {
        return $this->currency->toCurrency(sprintf("%f", $price));
    }
}
