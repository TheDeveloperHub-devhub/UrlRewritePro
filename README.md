# UrlRewritePro

### Features
*   This extension will allow you to rewrite the URLs of all the stores available or the store that the user wants.
*	You can rewrite the URLs of all the categories or the selective categories for specific stores or for all the stores.
*	This extension gives us the feature to rewrite the specific product URL with any store the user wants.


## Installation

1. Please run the following command
```shell
composer require developerhub/url-rewrite-pro
```

2. Update the composer if required
```shell
composer update
```

3. Enable module
```shell
php bin/magento module:enable DeveloperHub_Core
php bin/magento module:enable DeveloperHub_UrlRewritePro
php bin/magento setup:upgrade
php bin/magento cache:clean
php bin/magento cache:flush
```
4.If your website is running in product mode the you need to deploy static content and
then clear the cache
```shell
php bin/magento setup:static-content:deploy
php bin/magento setup:di:compile
```



#####This extension is compatible with all the versions of Magento 2.3.* and 2.4.*.
###Tested on following instances:
#####multiple instances i.e. 2.3.7-p3 and 2.4.5-p1
