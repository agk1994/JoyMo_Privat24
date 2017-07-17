<?php

class Magk_Privat24_Model_Payment extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'privat24';

    protected $_formBlockType = 'privat24/form';
    protected $_allowCurrencyCode = array('EUR', 'UAH', 'USD');
    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    /**
     * Set Redirect url after place order
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('privat24/index/redirect', array('_secure' => true));
    }

    /**
     * Get privat24 api url
     * @return string
     */
    public function getPrivat24Url()
    {
        return 'https://api.privatbank.ua/p24api/ishop';
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get redirect form fields
     *
     * @return array
     */
    public function getFormFields()
    {

        $order_id = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        $amount = trim(round($order->getGrandTotal(), 2));
        $currency_code = 'UAH';
        /**
         * TODO extend for multi currency
         * $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
         */

        $data = array(
            'amt' => sprintf('%.2f', $amount),
            'ccy' => $currency_code,
            'merchant' => $this->getConfigData('merchant_id'),
            'order' => $order_id,
            'details' => 'Payment for order ' . $order_id,
            'ext_details' => '',
            'pay_way' => 'privat24',
            'return_url' => Mage::getUrl('privat24/index/returnSuccess', array('_secure' => true)),
            'server_url' => Mage::getUrl('privat24/index/notification', array('_secure' => true)),
        );
        return $data;
    }

    /**
     * Save payment response and create invoice
     *
     * @param $request
     */
    public function notification($request)
    {
        $request_signature = $request['signature'];
        $request_payment = $request['payment'];
        $signature = sha1(md5($request_payment . $this->getConfigData('merchant_pass')));

        preg_match('/state=([a-z]+)/', $request['payment'], $state);
        $order_status = $state[1];
        preg_match('/order=([\d]+)/', $request['payment'], $order);
        $order_id = $order[1];
        preg_match('/sender_phone=([\d]+)/', $request['payment'], $sender_phone_a);
        $sender_phone = $sender_phone_a[1];
        preg_match('/amt=([0-9.]+)/', $request['payment'], $amount_a);
        $amount = $amount_a[1];
        preg_match('/ccy=([A-z]+)/', $request['payment'], $currency_a);
        $currency = $currency_a[1];
        preg_match('/ref=([a-zA-Z0-9]+)/', $request['payment'], $ref_a);
        $transaction_id = $ref_a[1];

        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

        if ($request_signature != $signature) {
            $order->addStatusToHistory(
                $order->getStatus(), Mage::helper('privat24')->__('Security check failed!')
            )->save();
            return;
        }
        if (!$order->getId()) {
            die();
        }

        // Get from config order status to be set
        $newOrderStatus = $this->getConfigData('order_status', $order->getStoreId());
        if (empty($newOrderStatus)) {
            $newOrderStatus = $order->getStatus();
        }

        // Send New order e-mail to customer
        $order->sendNewOrderEmail();
        $order->setEmailSent(true);
        $order->save();

        if ($order_status == 'ок' || $order_status == 'test') {
            if (!$order->canInvoice()) {
                // When order cannot create invoice, need to have some logic to take care
                $order->addStatusToHistory(
                    $order->getStatus(), Mage::helper('privat24')->__('Error during creation of invoice.', true), $notified = true
                );
            } else {
                $invoice = $order->prepareInvoice();
                $invoice->register()->pay();
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                // Send invoice email
                $invoice->sendEmail();
                $invoice->setEmailSent(true);
                $invoice->save();

                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, Mage::helper('privat24')->__('Invoice #%s created.', $invoice->getIncrementId()), $notified = true);

                $sDescription = '';
                $sDescription .= 'Captured amount of ' . $currency . ' ' . $amount . ' online. Transaction ID: "' . $transaction_id . '" <br />';
                $sDescription .= 'sender phone: ' . $sender_phone . '; ';

                $order->addStatusToHistory(
                    $order->getStatus(),
                    $sDescription
                )->save();
            }
        } elseif ($order_status == 'wait') {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING, $newOrderStatus, Mage::helper('privat24')->__('Waiting for verification from the Privat24 side.'), $notified = true
            );
        } elseif ($order_status == 'fail') {
            $order->setState(
                Mage_Sales_Model_Order::STATE_CANCELED, $newOrderStatus, Mage::helper('privat24')->__('Privat24 error.'), $notified = true
            );
        }
        $order->save();
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }
}
?>