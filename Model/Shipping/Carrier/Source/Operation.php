<?php
/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    yellowcube
 * @package     Swisspost_yellowcube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Model\Shipping\Carrier\Source;

/**
 * Class Swisspost_YellowCube_Model_Shipping_Carrier_Source_Operation
 */
class Operation
{
    const MODE_TESTING = 'T';
    const MODE_DEVELOPMENT = 'D';
    const MODE_PRODUCTION = 'P';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::MODE_TESTING,
                'label' => __('Test')
            ],
            [
                'value' => self::MODE_DEVELOPMENT,
                'label' => __('Development')
            ],
            [
                'value' => self::MODE_PRODUCTION,
                'label' => __('Production')
            ]
        ];
    }
}
