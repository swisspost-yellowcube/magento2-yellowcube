<?php

namespace Swisspost\YellowCube\Model\Dimension\Uom\Attribute;


class Source extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Option values
     */
    const VALUE_MTR = 'mtr';
    const VALUE_CMT = 'cmt';

    /**
     * Retrieve all options array
     *
     * @return array
     */
    public function getAllOptions()
    {
        $productTypes = Mage::getConfig()->getNode('global/catalog/product/type')->asArray();

        if (is_null($this->_options))
        {
            $this->_options = array();
            foreach(Mage::getConfig()->getNode('global/carriers/yellowcube/dimension/uom')->asArray() as $key => $elements)
            {
                $this->_options[] =
                    array(
                        'label' => __($elements['label']),
                        'value' => $key
                    );
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
