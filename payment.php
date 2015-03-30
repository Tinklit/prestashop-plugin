<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/tinklit.php');

if (!$cookie->isLogged(true))
  Tools::redirect('authentication.php?back=order.php');

$tinklit = new tinklit();

if (_PS_VERSION_ <= '1.5')
  echo $tinklit->execPayment($cart);
else
  Tools::redirect(Context::getContext()->link->getModuleLink('tinklit', 'payment'));

include_once(dirname(__FILE__).'/../../footer.php');

?>