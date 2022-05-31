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
 * @author    Tinklit <info@tinkl.it>
 * @copyright 2022 Tinkl.it
 * @license   https://github.com/Tinklit/tinkl-it-prestashop-payment-gateway/blob/master/LICENSE  The MIT License (MIT)
 */

require_once(_PS_MODULE_DIR_ . '/tinklit/vendor/tinklit/init.php');
require_once(_PS_MODULE_DIR_ . '/tinklit/vendor/version.php');

class TinklitCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        foreach($_POST as $key => $value)
        {
            $this->debugError($key . ' : ' . $value, $_POST['order_id']);
        }
        $cart_id = (int)Tools::getValue('order_id');
        $order_id = Order::getOrderByCartId($cart_id);
        $order = new Order($order_id);
        $sql = 'SELECT guid FROM '._DB_PREFIX_.'tinklit_order WHERE order_id = '.Tools::getValue('order_id');
        $guid = Db::getInstance()->getValue($sql);

        try {
            if (!$guid) {
                $error_message = 'No TINKLIT GUID associated to PS Order #' . Tools::getValue('order_id');
    
                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            if (!$order) {
                $error_message = 'Tinklit Order #' . Tools::getValue('order_id') . ' does not exists';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            $client_id = Configuration::get('TINKLIT_API_CLIENT_ID');
            $auth_token = Configuration::get('TINKLIT_API_TOKEN');
            
            $tnklConfig = array(
                'client_id' => $client_id,
                'token' => $auth_token,
                'environment' => (int)(Configuration::get('TINKLIT_TEST')) == 1 ? 'staging' : 'live',
                'user_agent' => 'Tinklit - Prestashop v'._PS_VERSION_.' Extension v'.TINKLIT_PRESTASHOP_EXTENSION_VERSION
              );

            \Tinklit\Tinklit::config($tnklConfig);
            $invoice = \Tinklit\Merchant\Invoice::find($guid);

            if (!$invoice) {
                $error_message = 'Tinklit Invoice GUID ' . $guid . ' does not exists';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            if ($order->id_cart != $invoice->order_id) {
                $error_message = 'Tinklit and PrestaShop orders do not match';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            // $this->debugLog($invoice->status, Tools::getValue('order_id'));
            switch ($invoice->status) {
                case 'payed':
                    $order_status = 'PS_OS_PAYMENT';
                    break;
                case 'pending':
                    $order_status = 'TINKLIT_PENDING';
                    break;
                case 'partial':
                    $order_status = 'TINKLIT_PARTIAL';
                    break;
                case 'expired':
                    $order_status = 'PS_OS_CANCELED';
                    break;
                case 'error':
                    $order_status = 'PS_OS_CANCELED';
                    break;
                default:
                    $order_status = false;
            }

            if ($order_status !== false) {
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState((int)Configuration::get($order_status), $order->id);
                $history->addWithemail(true, array(
                    'order_name' => Tools::getValue('order_id'),
                ));

                $this->context->smarty->assign(array(
                    'text' => 'OK'
                ));
            } else {
                $this->context->smarty->assign(array(
                    'text' => 'Order Status ' . $invoice->status . ' not implemented'
                ));
            }
        } catch (Exception $e) {
            $this->context->smarty->assign(array(
                'text' => get_class($e) . ': ' . $e->getMessage()
            ));
        }

        if (_PS_VERSION_ >= '1.7') {
            $this->setTemplate('module:tinklit/views/templates/front/payment_callback.tpl');
        } else {
            $this->setTemplate('payment_callback.tpl');
        }
    }

    private function logError($message, $cart_id)
    {
        PrestaShopLogger::addLog($message, 3, null, 'Cart', $cart_id, true);
    }

    private function debugLog($message, $id)
    {
        PrestaShopLogger::addLog($message, 2, null, 'Tinklit callback', $id, true);
    }
}
