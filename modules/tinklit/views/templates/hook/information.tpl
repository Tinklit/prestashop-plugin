{*
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
*}
<div class="tab">
  <button class="tablinks" onclick="changeTab(event, 'Information')" id="defaultOpen">{l s='Information' mod='tinklit'}</button>
  <button class="tablinks" onclick="changeTab(event, 'Configure Settings')">{l s='Configure Settings' mod='tinklit'}</button>
</div>

<!-- Tab content -->
<div id="Information" class="tabcontent">
	<div class="wrapper">
	  <img src="../modules/tinklit/views/img/invoice.png" style="float:right;"/>
	  <h2 class="tinklit-information-header">
      {l s='Accept Bitcoin & Lightning Network on your PrestaShop store with tinkl.it' mod='tinklit'}
    </h2><br/>
	  <strong>{l s='What is tinkl.it?' mod='tinklit'}</strong> <br/>
	  <p>
      {l s='tinkl.it is a bitcoin payment processor for merchants. Easily accepting Bitcoin & Lightning Network and get paid in EURO directly to your bank account.' mod='tinklit'}
    </p><br/>
	  <strong>{l s='Getting started' mod='tinklit'}</strong><br/>
	  <p>
	  	<ul>
	  		<li>{l s='Install the tinkl.it module on PrestaShop' mod='tinklit'}</li>
	  		<li>
          {l s='Visit ' mod='tinklit'}<a href="https://tinkl.it" target="_blank">{l s='tinkl.it' mod='tinklit'}</a>
          {l s='and create an account' mod='tinklit'}
         </li>
				 <li>{l s='Verify your merchant account' mod='tinklit'}</li>
	  		<li>{l s='Get your API credentials and copy-paste them to the Configuration page in Tinklit module' mod='tinklit'}</li>
	  	</ul>
	  </p>
	  <img src="../modules/tinklit/logo.png" style="float:right;"/>
	  <p class="sign-up"><br/>
	  	<a href="https://tinkl.it/register" class="sign-up-button">{l s='Sign up on tinkl.it' mod='tinklit'}</a>
	  </p><br/>
	  <strong>{l s='Features' mod='tinklit'}</strong>
	  <p>
	  	<ul>
	  		<li>{l s='The gateway is fully automatic - set and forget it.' mod='tinklit'}</li>
	  		<li>{l s='Payment amount is calculated using real-time exchange rates' mod='tinklit'}</li>
	  		<li>{l s='Your customers can pay with Bitcoin at checkout, while your payouts are in euros currency.' mod='tinklit'}</li>
	  		<li>{l s='Supports traditional on-chain transactions using the new native SegWit addresses (bech32)' mod='tinklit'}</li>
	  		<li>{l s='Supports Lightning Network payments.' mod='tinklit'}</li>
	  		<li>
          <a href="https://staging.tinkl.it" target="_blank">
            {l s='Staging environment' mod='tinklit'}
          </a> {l s='for testing with Testnet Bitcoin.' mod='tinklit'}
        </li>
				<li>{l s='No fees' mod='tinklit'}</li>
	  		<li>{l s='No chargebacks - guaranteed!' mod='tinklit'}</li>
	  	</ul>
	  </p>

	  <p><i>{l s='Questions? Contact our support info@tinkl.it' mod='tinklit'}</i></p>
	</div>
</div>

<div id="Configure Settings" class="tabcontent">
  {html_entity_decode($form|escape:'htmlall':'UTF-8')}
</div>

<script>
	document.getElementById("defaultOpen").click();
</script>
