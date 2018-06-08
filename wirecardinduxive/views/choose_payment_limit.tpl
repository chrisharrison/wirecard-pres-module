<p class="payment_module">
	<a href="#" id="disabled_payment" title="Pay by card (via Wirecard)">
		<img src="{$root_uri}img/payment_method.png" alt="Pay by card (via Wirecard)" />
		Pay by card (via Wirecard) {$currency_text}
	</a>
</p>

{literal}
<script>
$("#disabled_payment").click(function() {
	alert("This payment method is disabled because the toal transaction is either too low or too high.");
	return false;
});
</script>
{/literal}