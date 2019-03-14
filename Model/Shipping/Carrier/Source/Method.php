<?php

namespace Swisspost\YellowCube\Model\Shipping\Carrier\Source;

class Method
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $arr = array();
        foreach ($this->getMethods() as $key => $value) {
            $arr[] = array('value' => $key, 'label' => $value);
        }
        return $arr;
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        $result = array();
        foreach(Mage::getConfig()->getNode('global/carriers/yellowcube/methods')->asArray() as $methodData) {
            if (!isset($methodData['code']) || !isset($methodData['label'])) {
                continue;
            }
            $result[$methodData['code']] = $methodData['label'];
        }

        return $result;
    }
}
