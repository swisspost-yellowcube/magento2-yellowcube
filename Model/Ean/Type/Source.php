<?php

namespace Swisspost\YellowCube\Model\Ean\Type;

class Source extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{


    /**
     * Retrieve all options array
     *
     * @return array
     */
    public function getAllOptions()
    {

        if (is_null($this->_options)) {
            $this->_options = array();
            foreach(Mage::getConfig()->getNode('global/carriers/yellowcube/ean/type')->asArray() as $key => $elements)
            {
                $this->_options[] =
                    array(
                        'label' => __($elements['label']),
                        'value' => strtoupper($key)
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




