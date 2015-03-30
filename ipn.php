<?php

/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2015 Tinklit
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

// called by notification_url
include_once(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/../../header.php');
include_once(dirname(__FILE__).'/tinklit.php');


$handle = fopen('php://input','r');
$jsonInput = fgets($handle);
	
if (function_exists('json_decode')) {
  $decoded = json_decode($jsonInput, true);
} else {
  $decoded = rmJSONdecode($jsonInput);
}

fclose($handle);

// remember, item_code is cart id	
$order = (int)Order::getOrderByCartId((int)$decoded['item_code']);

$tinklit = new tinklit();		
    
if (in_array($decoded['status'], array('payed'))) {
  if ($order == 0) {
    p("order is 0");
    $customer_securekey = $decoded['order_id'];
    $tinklit->validateOrder($decoded['item_code'], Configuration::get('PS_OS_PAYMENT'), $decoded['price'], $tinklit->displayName, null, array(), null, false, $customer_securekey);
  } else {
      if (empty(Context::getContext()->link))
      Context::getContext()->link = new Link(); // workaround a prestashop bug so email is sent 
    $key = $decoded['order_id'];
    $order = new Order((int)Order::getOrderByCartId((int)$decoded['item_code']));
    $new_history = new OrderHistory();
    $new_history->id_order = (int)$order->id;
    //    $order_status = (int)Configuration::get('PS_OS_PAYMENT');
    $order_status = _PS_OS_PAYMENT_;
    $new_history->changeIdOrderState((int)$order_status, $order);
    $new_history->id_order_state = (int)$order_status;
    $new_history->addWithemail(true);
  }
}

$tinklit->writeDetails($tinklit->currentOrder, $decoded['item_code'], $decoded['guid'], $decoded['status']);
