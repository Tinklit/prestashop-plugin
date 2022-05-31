<?php
/**
* NOTICE OF LICENSE
*
* The MIT License (MIT)
*
* Copyright (c) 2022 Tinkl.it
*
* Permission is hereby granted, free of charge, to any person obtaining a copy of
* this software and associated documentation files (the "Software"), to deal in
* the Software without restriction, including without limitation the rights to use,
* copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
* and to permit persons to whom the Software is furnished to do so, subject
* to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
* WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
* IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*
*  @author    Tinklit <info@tinkl.it>
*  @copyright 2022 Tinkl.it
*  @license   https://github.com/Tinklit/tinkl-it-prestashop-payment-gateway/blob/master/LICENSE  The MIT License (MIT)
*/

require_once(_PS_MODULE_DIR_ . '/tinklit/vendor/tinklit/init.php');
require_once(_PS_MODULE_DIR_ . '/tinklit/vendor/version.php');
define('TINKLIT_INVOICE_PATH', 'https://api.tinkl.it/invoices/' );
define('TINKLIT_STAGING_INVOICE_PATH', 'https://api-staging.tinkl.it/invoices/' );

class TinklitRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $currency = Context::getContext()->currency;

        $description = array();
        foreach ($cart->getProducts() as $product) {
            $description[] = $product['cart_quantity'] . ' × ' . $product['name'];
        }

        $customer = new Customer($cart->id_customer);

        $link = new Link();
        $success_url = $link->getPageLink('order-confirmation', null, null, array(
          'id_cart'     => $cart->id,
          'id_module'   => $this->module->id,
          'key'         => $customer->secure_key
        ));

        $client_id = Configuration::get('TINKLIT_API_CLIENT_ID');
        $auth_token = Configuration::get('TINKLIT_API_TOKEN');
        
        $tnklConfig = array(
          'client_id' => $client_id,
          'token' => $auth_token,
          'environment' => (int)(Configuration::get('TINKLIT_TEST')) == 1 ? 'staging' : 'live',
          'user_agent' => 'Tinklit - Prestashop v'._PS_VERSION_.' Extension v'.TINKLIT_PRESTASHOP_EXTENSION_VERSION
        );

        \Tinklit\Tinklit::config($tnklConfig);

        $invoice = \Tinklit\Merchant\Invoice::create(array(
            'price'             => $total,
            'currency'          => $currency->iso_code,
            'deferred'          => false,
            'time_limit'        => 900,
            'order_id'          => $cart->id,
            'item_code'         => join($description, ', '),
            'cancel_url'        => $this->context->link->getModuleLink('tinklit', 'cancel'),
            'notification_url'  => $this->context->link->getModuleLink('tinklit', 'callback') . '?order_id='.(string)$cart->id,
            'redirect_url'      => $success_url
        ));
        // $this->debugLog($invoice->redirect_url, $cart->id);
        if ($invoice) {
            if (!$invoice->guid) {
                Tools::redirect('index.php?controller=order&step=3');
            }

            $customer = new Customer($cart->id_customer);
            $this->module->validateOrder(
                $cart->id,
                Configuration::get('TINKLIT_PENDING'),
                $total,
                $this->module->displayName,
                null,
                null,
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            $insertTinklitData = array(
                'id_tinklit_order' => $cart->id,
                'order_id'  => $cart->id, 
                'guid'  => $invoice->guid
             );
       
            $result = Db::getInstance()->insert("tinklit_order", $insertTinklitData);
            if (!$result) {
                Tools::redirect('index.php?controller=order&step=3');
            }
            
            if ((int)(Configuration::get('TINKLIT_TEST')) == 1) {
                Tools::redirect(TINKLIT_STAGING_INVOICE_PATH . $invoice->guid);
            } else {
                Tools::redirect(TINKLIT_INVOICE_PATH . $invoice->guid);
            }
        } else {
            Tools::redirect('index.php?controller=order&step=3');
        }
    }

    private function debugLog($message, $id)
    {
        PrestaShopLogger::addLog($message, 2, null, 'tinklit redirect', $id, true);
    }
}
