<?php

/**
 * Mestrona magento module
 *
 * LICENSE
 *
 * This source file is subject of Mestrona.
 * You may be not allowed to change the sources
 * without authorization of Mestrona GbR.
 *
 * @copyright  Copyright (c) 2012 Mestrona GbR (http://www.mestrona.net)
 * @author Mestrona GbR <support@mestrona.net>
 * @category Mestrona
 * @package Mestrona_ForwardToConfigurable
 */

class Mestrona_ForwardToConfigurable_Model_Observer extends Mage_Core_Model_Abstract
{

    /**
     * Generates config array to reflect the simple product's ($currentProduct)
     * configuration in its parent configurable product
     *
     * @param Mage_Catalog_Model_Product $parentProduct
     * @param Mage_Catalog_Model_Product $currentProduct
     * @return array array( configoptionid -> value )
     */
    protected function generateConfigData(Mage_Catalog_Model_Product $parentProduct, Mage_Catalog_Model_Product $currentProduct)
    {
        /* @var $typeInstance Mage_Catalog_Model_Product_Type_Configurable */
        $typeInstance = $parentProduct->getTypeInstance();
        if (!$typeInstance instanceof Mage_Catalog_Model_Product_Type_Configurable) {
            return; // not a configurable product
        }
        $configData = array();
        $attributes = $typeInstance->getUsedProductAttributes($parentProduct);

        foreach ($attributes as $code => $data) {
            $configData[$code] = $currentProduct->getData($data->getAttributeCode());
        }

        return $configData;
    }

    /**
     * Checks if the current product has a super-product assigned
     * Finds the super product
     * @param $observer Varien_Event_Observer $observer
     * @throws Exception
     */
    public function forwardToConfigurable($observer)
    {
        $controller = $observer->getControllerAction();
        $productId = (int)$controller->getRequest()->getParam('id');

        $parentIds = Mage::getModel('catalog/product_type_configurable')
            ->getParentIdsByChild($productId);
        if (empty($parentIds)) { // does not have a parent -> nothing to do
            return;
        }

        while (count($parentIds) > 0) {
            $parentId = array_shift($parentIds);
            /* @var $parentProduct Mage_Catalog_Model_Product */
            $parentProduct = Mage::getModel('catalog/product');
            $parentProduct->load($parentId);
            if (!$parentProduct->getId()) {
                throw new Exception(sprintf('Can not load parent product with ID %d', $parentId));
            }

            if ($parentProduct->isVisibleInCatalog()) {
                break;
            }
            // try to find other products if one parent product is not visible -> loop
        }

        if (!$parentProduct->isVisibleInCatalog()) {
            Mage::log(sprintf('Not enabled parent for product id %d found.', $productId), Zend_Log::WARN);
            return;
        }

        if (!empty($parentIds)) {
            Mage::log(sprintf('Product with id %d has more than one enabled parent. Choosing first.', $productId), Zend_Log::NOTICE);
        }


            /* @var $currentProduct Mage_Catalog_Model_Product */
        $currentProduct = Mage::getModel('catalog/product');
        $currentProduct->load($productId);

        $params = new Varien_Object();
        $params->setCategoryId(false);
        $params->setConfigureMode(true);
        $buyRequest = new Varien_Object();
        $buyRequest->setSuperAttribute($this->generateConfigData($parentProduct, $currentProduct)); // example format: array(525 => "99"));
        $params->setBuyRequest($buyRequest);

        // override visibility setting of configurable product
        // in case only simple products should be visible in the catalog
        // TODO: make this behaviour configurable
        $params->setOverrideVisibility(true);

        /* @var $productViewHelper Mage_Catalog_Helper_Product_View */
        $productViewHelper = Mage::helper('catalog/product_view');

        $controller->getRequest()->setDispatched(true);
        // avoid double dispatching
        // @see Mage_Core_Controller_Varien_Action::dispatch()
        $controller->setFlag('', Mage_Core_Controller_Front_Action::FLAG_NO_DISPATCH, true);


        $productViewHelper->prepareAndRender($parentId, $controller, $params);
    }

}