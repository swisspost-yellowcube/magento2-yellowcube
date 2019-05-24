<?php

namespace Swisspost\YellowCube\Ui\Component\Listing\Columns;

use Magento\Framework\Data\OptionSourceInterface;
use Swisspost\YellowCube\Model\Shipping\Carrier\Carrier;

/**
 * Swisspost YellowCube Status Option Source
 */
class ShipmentStatusOptions implements OptionSourceInterface
{
    /**
     * @var Carrier
     */
    private $carrier;

    /**
     * ShipmentStatusOptions constructor.
     * @param Carrier $carrier
     */
    public function __construct(Carrier $carrier)
    {
        $this->carrier = $carrier;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->carrier->getShipmentStatusOptions() as $code => $text) {
            $options[] = [
                'value' => $code,
                'label' => $text,
            ];
        }

        return $options;
    }
}
