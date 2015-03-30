<?php

class tinklitPaymentModuleFrontController extends ModuleFrontController
{
  public $ssl = true;
  public $display_column_left = true;

  /**
   * @see FrontController::initContent()
   */
  public function initContent()
  {
    parent::initContent();

    $cart = $this->context->cart;

    echo $this->module->execPayment($cart);
  }
}


