<?php

namespace Swisspost\YellowCube\Helper;

trait FormatHelper
{
    /**
     * @param float $number
     * @return string
     */
    public function formatUom($number)
    {
        return number_format($number, 3, '.', '');
    }

    /**
     * @param $description
     * @return string
     */
    public function formatDescription($description)
    {
        return mb_strcut($description, 0, 40);
    }

    /**
     * @param string $sku
     * @return string
     */
    public function formatSku($sku)
    {
        return str_replace(' ', '_', $sku);
    }

    /**
     * @param $string
     * @return string
     */
    public function cutString($string, $length = 35)
    {
        return mb_strcut($string, 0, $length);
    }

    /**
     * @param $elem
     * @param $array
     * @return bool
     */
    public function inMultiArray($elem, $array)
    {
        foreach ($array as $key => $value) {
            if ($value == $elem) {
                return true;
            } elseif (is_array($value)) {
                if ($this->inMultiArray($elem, $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
