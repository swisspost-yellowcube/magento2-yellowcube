<?php

namespace Swisspost\YellowCube\Model\Ean\Type;

use Swisspost\YellowCube\Helper\Data;

class Source extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{


    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var array
     */
    protected $_attributeProductIds;

    /**
     * HandleProductSaveBefore constructor.
     * @param Data $dataHelper
     */
    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * Retrieve all options array
     *
     * @return array
     */
    public function getAllOptions()
    {

        if (is_null($this->_options)) {
            $this->_options = array();
            foreach ($this->dataHelper->getEanTypes() as $key => $elements) {
                $this->_options[] = [
                    'label' => __($elements['label']),
                    'value' => strtoupper($key)
                ];
            }
        }
        return $this->_options;
    }

    /**
     * Get a text for option value
     *
     * @param string|integer $value
     * @return string
     */
    public function getOptionText($value)
    {
        $options = $this->getAllOptions();
        foreach ($options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }
}




