<img src="{$root_uri}img/logo.png">
<br><br>
<h3>Pay by card (via Wirecard)</h3>
<p>You're about to leave {$shop_name} and be redirected to Wirecard's secure payment gateway. When payment is complete you'll be transferred back to {$shop_name}.</p>
<p>Total amount to pay:  <strong>{displayPrice price=$paymentParams['amount']} {$paymentParams['currency']}</strong></p>
<p>
    <form action='{$paymentUrl}' method='post' name='checkout' target='_self'>
        {foreach from=$paymentParams key=key item=value}
            <input type='hidden' name='{$key}' value='{$value}' />
        {/foreach}
        <input type="submit" class="btn btn-primary pull-right" value="Pay">
    </form>
</p>