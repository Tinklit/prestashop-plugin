<?php
/**
 * NOTICE OF LICENSE
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2022 Tinklit
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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . '/tinklit/vendor/tinklit/init.php';
require_once _PS_MODULE_DIR_ . '/tinklit/vendor/version.php';

class Tinklit extends PaymentModule
{
    private $html = '';
    private $postErrors = array();

    public $api_token;
    public $client_id;
    public $test;

    public function __construct()
    {
        $this->name = 'tinklit';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->author = 'tinkl.it';
        $this->is_eu_compatible = 1;
        $this->controllers = array('payment', 'redirect', 'callback', 'cancel');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        $config = Configuration::getMultiple(
            array(
                'TINKLIT_API_CLIENT_ID',
                'TINKLIT_API_TOKEN',
                'TINKLIT_TEST',
            )
        );

        if (!empty($config['TINKLIT_API_CLIENT_ID'])) {
            $this->client_id = $config['TINKLIT_API_CLIENT_ID'];
        }

        if (!empty($config['TINKLIT_API_TOKEN'])) {
            $this->api_token = $config['TINKLIT_API_TOKEN'];
        }

        if (!empty($config['TINKLIT_TEST'])) {
            $this->test = $config['TINKLIT_TEST'];
        }

        parent::__construct();

        $this->displayName = $this->l('Accept Bitcoin & Lightning Network with tinkl.it');
        $this->description = $this->l('Accept Bitcoin & Lightning Network as a payment method with tinkl.it');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        if (!isset($this->client_id)
            || !isset($this->receive_currency)) {
            $this->warning = $this->l('API CLIENT_ID Access details must be configured in order to use this module correctly.');
        }
        
        if (!isset($this->api_token)
            || !isset($this->receive_currency)) {
            $this->warning = $this->l('API TOKEN Access details must be configured in order to use this module correctly.');
        }
    }

    public function install()
    {
        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('This module requires cURL PHP extension in order to function normally.');

            return false;
        }

        $order_pending = new OrderState();
        $order_pending->name = array_fill(0, 10, 'tinkl.it pending payment');
        $order_pending->send_email = 0;
        $order_pending->invoice = 0;
        $order_pending->color = 'RoyalBlue';
        $order_pending->unremovable = false;
        $order_pending->logable = 0;

        $order_partial = new OrderState();
        $order_partial->name = array_fill(0, 10, 'tinkl.it partial payment');
        $order_partial->send_email = 0;
        $order_partial->invoice = 0;
        $order_partial->color = '#FFFF00';
        $order_partial->unremovable = false;
        $order_partial->logable = 0;

        $order_expired = new OrderState();
        $order_expired->name = array_fill(0, 10, 'tinkl.it payment expired');
        $order_expired->send_email = 0;
        $order_expired->invoice = 0;
        $order_expired->color = '#E40000';
        $order_expired->unremovable = false;
        $order_expired->logable = 0;

        $order_error = new OrderState();
        $order_error->name = array_fill(0, 10, 'tinkl.it invoice is invalid');
        $order_error->send_email = 0;
        $order_error->invoice = 0;
        $order_error->color = '#E40000';
        $order_error->unremovable = false;
        $order_error->logable = 0;

        if ($order_pending->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/tinklit/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_pending->id . '.gif'
            );
        }

        if ($order_partial->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/tinklit/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_partial->id . '.gif'
            );
        }

        if ($order_expired->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/tinklit/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_expired->id . '.gif'
            );
        }

        if ($order_error->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/tinklit/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_error->id . '.gif'
            );
        }

        Configuration::updateValue('TINKLIT_PENDING', $order_pending->id);
        Configuration::updateValue('TINKLIT_PARTIAL', $order_partial->id);
        Configuration::updateValue('TINKLIT_EXPIRED', $order_expired->id);
        Configuration::updateValue('TINKLIT_ERROR', $order_error->id);

        if (!parent::install()
            || !$this->installDb()
            || !$this->registerHook('payment')
            || !$this->registerHook('displayPaymentEU')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        $order_state_pending = new OrderState(Configuration::get('TINKLIT_PENDING'));
        $order_state_partial = new OrderState(Configuration::get('TINKLIT_PARTIAL'));
        $order_state_expired = new OrderState(Configuration::get('TINKLIT_EXPIRED'));
        $order_state_error = new OrderState(Configuration::get('TINKLIT_ERROR'));

        return (
            Configuration::deleteByName('TINKLIT_APP_ID') &&
            Configuration::deleteByName('TINKLIT_API_KEY') &&
            Configuration::deleteByName('TINKLIT_API_CLIENT_ID') &&
            Configuration::deleteByName('TINKLIT_API_TOKEN') &&
            Configuration::deleteByName('TINKLIT_TEST') &&
            $order_state_pending->delete() &&
            $order_state_partial->delete() &&
            $order_state_expired->delete() &&
            $order_state_error->delete() &&
            $this->unInstallDb() &&
            parent::uninstall()
        );
    }

    public function installDb()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."tinklit_order`(
            `id_tinklit_order` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT( 11 ) NOT NULL,
            `guid` varchar(255) NOT NULL )
            ";
            
        if($result=Db::getInstance()->Execute($sql)){
            return true;
        } else {
            return false;
        } 
    }

    public function unInstallDb()
    {
        $sql = "DROP TABLE IF EXISTS `"._DB_PREFIX_."tinklit_order`";
        if($result=Db::getInstance()->Execute($sql)){
            return true;
        } else {
            return false;
        } 
    }

    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('TINKLIT_API_CLIENT_ID')) {
                $this->postErrors[] = $this->l('API CLIENT ID is required.');
            }
            
            if (!Tools::getValue('TINKLIT_API_TOKEN')) {
                $this->postErrors[] = $this->l('API TOKEN is required.');
            }
        }
    }

    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            
            Configuration::updateValue(
                'TINKLIT_API_CLIENT_ID',
                $this->stripString(Tools::getValue('TINKLIT_API_CLIENT_ID'))
            );
            Configuration::updateValue(
                'TINKLIT_API_TOKEN',
                $this->stripString(Tools::getValue('TINKLIT_API_TOKEN'))
            );
            Configuration::updateValue('TINKLIT_TEST', Tools::getValue('TINKLIT_TEST'));
        }

        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    private function displayTinklit()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    private function displayTinklitInformation($renderForm)
    {
        $this->html .= $this->displayTinklit();
        $this->context->controller->addCSS($this->_path . '/views/css/tabs.css', 'all');
        $this->context->controller->addJS($this->_path . '/views/js/javascript.js', 'all');
        $this->context->smarty->assign('form', $renderForm);
        return $this->display(__FILE__, 'information.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        } else {
            $this->html .= '<br />';
        }

        $renderForm = $this->renderForm();
        $this->html .= $this->displayTinklitInformation($renderForm);

        return $this->html;
    }

    public function hookPayment($params)
    {
        if (_PS_VERSION_ >= 1.7) {
            return;
        }
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $this->smarty->assign(array(
        'this_path'     => $this->_path,
        'this_path_bw'  => $this->_path,
        'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookDisplayOrderConfirmation($params)
    {
        if (_PS_VERSION_ <= 1.7) {
            return;
        }

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));

        return $this->context->smarty->fetch(__FILE__, 'payment.tpl');
    }


    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        if (_PS_VERSION_ < 1.7) {
            $order = $params['objOrder'];
            $state = $order->current_state;
        } else {
            $state = $params['order']->getCurrentState();
        }
        $this->smarty->assign(array(
            'state' => $state,
            'paid_state' => (int)Configuration::get('PS_OS_PAYMENT'),
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));
        return $this->display(__FILE__, 'payment_return.tpl');
    }


    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText('Bitcoin & Lightning Network with tinkl.it')
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:tinklit/views/templates/hook/tinklit_intro.tpl')
            );

        $payment_options = array($newOption);

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Accept Bitcoin  & Lightning Network with tinkl.it'),
                    'icon' => 'icon-bitcoin',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Client ID'),
                        'name' => 'TINKLIT_API_CLIENT_ID',
                        'desc' => $this->l('Your API Client ID (created on tinkl.it)'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Token'),
                        'name' => 'TINKLIT_API_TOKEN',
                        'desc' => $this->l('Your Token (created on tinkl.it)'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Test Mode'),
                        'name' => 'TINKLIT_TEST',
                        'desc' => $this->l(
                            '
                                                To test on staging.tinkl.it, turn Test Mode “On”.
                                                Please note, for Test Mode you must create a separate account
                                                on staging.tinkl.it and generate new pos for ecommerce.
                                                CLIENT ID and TOKEN generated on tinkl.it are "Live" credentials
                                                and will not work for "Test" mode.'
                        ),
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 0,
                                    'name' => 'Off',
                                ),
                                array(
                                    'id_option' => 1,
                                    'name' => 'On',
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0);
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module='
            . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            
            'TINKLIT_API_CLIENT_ID' => $this->stripString(Tools::getValue(
                'TINKLIT_API_CLIENT_ID',
                Configuration::get('TINKLIT_API_CLIENT_ID')
            )),
            'TINKLIT_API_TOKEN' => $this->stripString(Tools::getValue(
                'TINKLIT_API_TOKEN',
                Configuration::get('TINKLIT_API_TOKEN')
            )),
            'TINKLIT_TEST' => Tools::getValue(
                'TINKLIT_TEST',
                Configuration::get('TINKLIT_TEST')
            ),
        );
    }

    private function stripString($item)
    {
        return preg_replace('/\s+/', '', $item);
    }
}
