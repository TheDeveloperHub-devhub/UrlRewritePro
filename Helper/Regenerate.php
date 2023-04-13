<?php

namespace DeveloperHub\UrlRewritePro\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Regenerate extends AbstractHelper
{
    /**
     * Get config value of "Use Categories Path for Product URLs" config option
     * @param  mixed $storeId
     * @return boolean
     */
    public function useCategoriesPathForProductUrls($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            'catalog/seo/product_use_categories',
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
    }
    /**
     * Sanitize product URL rewrites
     * @param  array $productUrlRewrites
     * @return array
     */
    public function sanitizeProductUrlRewrites($productUrlRewrites)
    {
        $paths = [];
        foreach ($productUrlRewrites as $key => $urlRewrite) {
            $path = $this->_clearRequestPath($urlRewrite->getRequestPath());
            if (!in_array($path, $paths)) {
                $productUrlRewrites[$key]->setRequestPath($path);
                $paths[] = $path;
            } else {
                unset($productUrlRewrites[$key]);
            }
        }
        return $productUrlRewrites;
    }

    /**
     * @param $requestPath
     * @return array|string|string[]
     */
    protected function _clearRequestPath($requestPath)
    {
        return str_replace(['//', './'], ['/', '/'], ltrim(ltrim($requestPath, '/'), '.'));
    }
}
