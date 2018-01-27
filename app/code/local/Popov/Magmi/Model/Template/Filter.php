<?php

/**
 * This class add {{specialchar}} template directive
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 04.06.2017 19:18
 */
class Popov_Magmi_Model_Template_Filter extends Mage_Cms_Model_Template_Filter
{
    /**
     * CDN media URL filter
     *
     * @param array $construction
     * @return string
     */
    public function specCharDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);

        /** @var Popov_Magmi_Helper_SpecialChar $specialHelper */
        $specialHelper = Mage::helper('popov_magmi/specialChar');

        $prepared = '';
        if (isset($params['encode']) && trim($params['encode'])) {
            $prepared = $specialHelper->encode($params['encode']);
        } elseif (isset($params['decode']) && trim($params['decode'])) {
            $prepared = $specialHelper->decode($params['encode']);
        }

        return $prepared;
    }
}