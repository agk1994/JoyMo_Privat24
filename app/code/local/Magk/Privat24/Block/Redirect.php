<?php

/**
 * Payment redirect block
 * Class Magk_Privat24_Block_Redirect
 */
class Magk_Privat24_Block_Redirect extends Mage_Core_Block_Abstract
{
    /**
     * Create redirect form with order data
     * 
     * @return string
     */
    protected function _toHtml()
    {
        $privat = Mage::getModel('privat24/payment');

        $form = new Varien_Data_Form();
        $form->setAction($privat->getPrivat24Url())
            ->setId('privat24_form')
            ->setName('privat24_form')
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($privat->getFormFields() as $name => $value) {
            $form->addField($name, 'hidden', array('name' => $name, 'value' => $value));
        }


        $submitButton = new Varien_Data_Form_Element_Submit(array(
            'value' => $this->__('Click here if you are not redirected within 10 seconds...'),
        ));
        $form->addElement($submitButton);
        $html = '<html><body>';
        $html .= $this->__('You will be redirected to the Privat24 website in a few seconds.');
        $html .= $form->toHtml();
        $html .= '<script type="text/javascript">document.getElementById("privat24_form").submit();</script>';
        $html .= '</body></html>';

        return $html;
    }
}
