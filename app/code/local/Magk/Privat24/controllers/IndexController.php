<?php

/**
 * Class Magk_Privat24_IndexController
 */
class Magk_Privat24_IndexController extends Mage_Core_Controller_Front_Action {

    /**
     * Order instance
     */
    protected $_order;

    /**
     *  Get order
     *
     *  @return	  Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($this->_order == null) {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = Mage::getModel('sales/order')
                ->loadByIncrementId($session->getLastRealOrderId());
        }
        return $this->_order;
    }

    public function getSession() {
        return Mage::getSingleton('checkout/session');
    }

    public function redirectAction() {

        $session = Mage::getSingleton('checkout/session');
        $last_real_order_id = $session->getLastRealOrderId();

        $session->setPrivat24QuoteId($session->getQuoteId());
        $session->setPrivat24LastRealOrderId($last_real_order_id);

        $this->getResponse()->setBody($this->getLayout()->createBlock('privat24/redirect')->toHtml());

        $order = $this->getOrder();
        $order->loadByIncrementId($last_real_order_id);

        // Add a message for Shop Admin about redirect action
        $order->addStatusToHistory(
            $order->getStatus(), Mage::helper('privat24')->__('User redirected to Privat24')
        )->save();

        $session->getQuote()->setIsActive(false)->save();

        // Clear Shopping Cart
        $session->setQuoteId(null);
        $session->setLastRealOrderId(null);
    }

    /**
     *
     * Customer successfully got back from Privat24 payment interface
     *
     */
    public function returnSuccessAction() {
        if(!$this->getRequest()->isPost()){
            $this->norouteAction();
            return;
        }
        // Call notification payment
        Mage::getModel('privat24/payment')->notification($this->getRequest()->getPost());


        $session = $this->getSession();
        $order_id = $session->getPrivat24LastRealOrderId();
        $quote_id = $session->getPrivat24QuoteId(true);

        $order = $this->getOrder();
        $order->loadByIncrementId($order_id);

        if ($order->isEmpty()) {
            return false;
        }

        // Add a message for Admin about customer returning uncumment this if need to show message
        /*$order->addStatusToHistory(
            $order->getStatus(), Mage::helper('privat24')->__('Customer successfully got back from Privat24 payment interface.')
        )->save();*/

        $session->setQuoteId($quote_id);
        $session->getQuote()->setIsActive(false)->save();
        $session->setLastRealOrderId($order_id);

        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }

    public function notificationAction(){
        if(!$this->getRequest()->isPost()){
            $this->norouteAction();
            return;
        }
        Mage::getModel('privat24/payment')->notification($this->getRequest()->getPost());
    }

}

?>
