<?php

class Mestrona_ForwardToConfigurable_Helper_Catalog_Product extends Mage_Catalog_Helper_Product
{

    /**
     * Copied from Magento 1.7.0.2
     *
     * Allow overriding the visibility flag
     *
     * @param int $productId
     * @param Mage_Core_Controller_Front_Action $controller
     * @param null $params
     * @return bool|false|Mage_Catalog_Model_Product
     *
     */
    public function initProduct($productId, $controller, $params = null)
    {
        // Prepare data for routine
        if (!$params) {
            $params = new Varien_Object();
        }

        // Init and load product
        Mage::dispatchEvent('catalog_controller_product_init_before', array(
            'controller_action' => $controller,
            'params' => $params,
        ));

        if (!$productId) {
            return false;
        }

        $product = Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($productId);


        // [Mestrona BEGIN]
        if (!$params->getOverrideVisibility()) {
            if (!$this->canShow($product)) {
                return false;
            }
        } else {
            if (!$product->isVisibleInCatalog()) {
                return false;
            }
        }
        // [Mestrona END]

        if (!in_array(Mage::app()->getStore()->getWebsiteId(), $product->getWebsiteIds())) {
            return false;
        }

        // Load product current category
        $categoryId = $params->getCategoryId();
        if (!$categoryId && ($categoryId !== false)) {
            $lastId = Mage::getSingleton('catalog/session')->getLastVisitedCategoryId();
            if ($product->canBeShowInCategory($lastId)) {
                $categoryId = $lastId;
            }
        } elseif (!$product->canBeShowInCategory($categoryId)) {
            $categoryId = null;
        }

        if ($categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $product->setCategory($category);
            Mage::register('current_category', $category);
        }

        // Register current data and dispatch final events
        Mage::register('current_product', $product);
        Mage::register('product', $product);

        try {
            Mage::dispatchEvent('catalog_controller_product_init', array('product' => $product));
            Mage::dispatchEvent('catalog_controller_product_init_after',
                array('product' => $product,
                    'controller_action' => $controller
                )
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            return false;
        }

        return $product;
    }


}
