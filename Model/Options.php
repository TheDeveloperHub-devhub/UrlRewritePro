<?php

namespace DeveloperHub\UrlRewritePro\Model;

use Magento\Framework\Data\OptionSourceInterface;

class Options implements OptionSourceInterface
{
    protected $attributeOptionsList = [];

    /** @return array */
    public function toOptionArray()
    {
        $this->attributeOptionsList = [
            [
                'value' => "Test 1",
                "label" => "Test 1",
                "__disableTmpl" => 1,
                "optgroup" => [
                    [
                        'value' => "Test 1.1",
                        "label" => "Test 1.1",
                        "__disableTmpl" => 1,
                    ],
                ],
            ],
        ];
        return $this->attributeOptionsList;
    }
}
