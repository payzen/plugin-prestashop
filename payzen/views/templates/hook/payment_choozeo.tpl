{**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for PrestaShop. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 *}

<!-- This meta tag is mandatory to avoid encoding problems caused by \PrestaShop\PrestaShop\Core\Payment\PaymentOptionFormDecorator -->
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

<form action="{$link->getModuleLink('payzen', 'redirect', array(), true)|escape:'html':'UTF-8'}" method="post" id="payzen_choozeo" style="margin-left: 2.875rem; margin-top: 1.25rem; margin-bottom: 1rem;">
  <input type="hidden" name="payzen_payment_type" value="choozeo">

  {assign var=first value=true}
  {foreach from=$payzen_choozeo_options key="key" item="option"}
    <label class="payzen_card_click" for="payzen_card_type_{$key|escape:'html':'UTF-8'}">
      <input type="radio" name="payzen_card_type" id="payzen_card_type_{$key|escape:'html':'UTF-8'}" value="{$key|escape:'html':'UTF-8'}" {if $first == true} checked="checked"{/if}>
      <img src="{$option['logo']}"
           alt="{$option['label']|escape:'html':'UTF-8'}"
           title="{$option['label']|escape:'html':'UTF-8'}">

      &nbsp;&nbsp;&nbsp;&nbsp;
    </label>

    {assign var=first value=false}
  {/foreach}
</form>

<!--[if IE]>
  <script type="text/javascript">
    $('#payzen_choozeo label.payzen_card_click img').on('click', function(e) {
      $(this).parent().click();
    });
  </script>
<![endif]-->