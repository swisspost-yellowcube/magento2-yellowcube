<?php
/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    Yellowcube
 * @package     Swisspost_YellowCube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Model\Shipping\Carrier\Source;

class Additional
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $arr = array(
            array('value' => 'SI', 'label' => __('Signature')),
            array('value' => 'AZ', 'label' => __('Evening delivery')),
            array('value' => 'SA', 'label' => __('Saturday delivery')),
            array('value' => 'APOST', 'label' => __('A-POST')),
            array('value' => 'INTPRI', 'label' => __('Priority International')),
            array('value' => 'INTECO', 'label' => __('Economy International')),
            array('value' => 'GR', 'label' => __('Gross')),
            array('value' => 'MX', 'label' => __('Maxi')),
        );

        return $arr;
    }
}
