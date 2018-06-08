<style>
    #enets_settings input[type=text], #enets_settings select {
        width: 300px;
        margin-left: 2em;
    }
    #enets_settings fieldset {
        margin-bottom: 1em;
    }
    #enets_settings td {
        padding-bottom: 1em;
    }
</style>

<img src="<?php echo $root_uri; ?>img/logo.png">

<br><br>

<?php if($success) { ?>
<div class="module_confirmation conf confirm">
    Settings updated
</div>
<?php } ?>

<form id="enets_settings" method="post" action="">
    
    <fieldset>
        <legend>Service parameters</legend>
        <table>
            <tbody>
                <tr>
                    <td>CustomerId</td>
                    <td><input type="text" name="customerId" value="<?php echo $form_values['customerId']; ?>"></td>
                </tr>
                <tr>
                    <td>Secret</td>
                    <td><input type="text" name="secret" value="<?php echo $form_values['secret']; ?>"></td>
                </tr>
                <tr>
                    <td>ShopId</td>
                    <td><input type="text" name="shopId" value="<?php echo $form_values['shopId']; ?>"></td>
                </tr>
                <tr>
                    <td>Service URL:</td>
                    <td><input type="text" name="service_url" value="<?php echo $form_values['service_url']; ?>"></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
    <fieldset>
        <legend>Response URLs</legend>
        <table>
            <tbody>
                <tr>
                    <td>Response:</td>
                    <td><input type="text" name="url_response" value="<?php echo $form_values['url_response']; ?>"></td>
                </tr>
                <tr>
                    <td>About page:</td>
                    <td><input type="text" name="url_about" value="<?php echo $form_values['url_about']; ?>"></td>
                </tr>
                <tr>
                    <td>Logo:</td>
                    <td><input type="text" name="url_logo" value="<?php echo $form_values['url_logo']; ?>"></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
    
    <fieldset>
        <legend>Payment statuses</legend>
        <table>
            <tbody>
                <tr>
                    <td>Pending status:</td>
                    <td>
                        <select name="order_status_pending">
                            <?php foreach($order_states as $state) { ?>
                            <option value="<?php echo $state['id_order_state']; ?>"<?php if($state['id_order_state'] == $form_values['order_status_pending']) { ?> selected="selected"<?php } ?>><?php echo $state['name']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Cancel status:</td>
                    <td>
                        <select name="order_status_cancel">
                            <?php foreach($order_states as $state) { ?>
                                <option value="<?php echo $state['id_order_state']; ?>"<?php if($state['id_order_state'] == $form_values['order_status_cancel']) { ?> selected="selected"<?php } ?>><?php echo $state['name']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Fail status:</td>
                    <td>
                        <select name="order_status_fail">
                            <?php foreach($order_states as $state) { ?>
                            <option value="<?php echo $state['id_order_state']; ?>"<?php if($state['id_order_state'] == $form_values['order_status_fail']) { ?> selected="selected"<?php } ?>><?php echo $state['name']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Complete status:</td>
                    <td>
                        <select name="order_status_complete">
                            <?php foreach($order_states as $state) { ?>
                            <option value="<?php echo $state['id_order_state']; ?>"<?php if($state['id_order_state'] == $form_values['order_status_complete']) { ?> selected="selected"<?php } ?>><?php echo $state['name']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </fieldset>

    <fieldset>
        <legend>Transaction limits</legend>
        <table>
            <tbody>
                <i>Leaving blank/zero will ignore the limit entirely.</i>
                <br><br>
                <tr>
                    <td>Minimum transaction value:</td>
                    <td><input type="text" style="width: 60px;" name="limit_min" value="<?php echo $form_values['limit_min']; ?>"></td>
                </tr>
                <tr>
                    <td>Maximum transaction value:</td>
                    <td><input type="text" style="width: 60px;" name="limit_max" value="<?php echo $form_values['limit_max']; ?>"></td>
                </tr>
                <tr>
                    <td>Limit action:</td>
                    <td>
                        <select name="limit_action">
                            <?php foreach($limit_actions as $action) { ?>
                            <option value="<?php echo $action['id']; ?>"<?php if($action['id'] == $form_values['limit_action']) { ?> selected="selected"<?php } ?>><?php echo $action['description']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </fieldset>
    
    <p><input type="submit" name="submit" value="Save" class="button"></p>
    
</form>