<?php
/**
 * Page Template
 *
 * Loaded automatically by index.php?main_page=checkout_payment.<br />
 * Displays the allowed payment modules, for selection by customer.
 *
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

echo $payment_modules->javascript_validation();
$Paygate = new Paygate();

?>
<div class="centerColumn" id="checkoutPayment">
    <?php
    echo zen_draw_form('checkout_payment', zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'), 'post'); ?>
    <?php
    echo zen_draw_hidden_field('action', 'submit'); ?>

    <h1 id="checkoutPaymentHeading"><?php
        echo HEADING_TITLE; ?></h1>

    <?php
    if ($messageStack->size('redemptions') > 0) {
        echo $messageStack->output('redemptions');
    } ?>
    <?php
    if ($messageStack->size('checkout') > 0) {
        echo $messageStack->output('checkout');
    } ?>
    <?php
    if ($messageStack->size('checkout_payment') > 0) {
        echo $messageStack->output('checkout_payment');
    } ?>

    <?php
    if (!$payment_modules->in_special_checkout()) {
        ?>
        <h2 id="checkoutPaymentHeadingAddress"><?php
            echo TITLE_BILLING_ADDRESS; ?></h2>

        <div id="checkoutBillto" class="floatingBox back">
            <?php
            if (MAX_ADDRESS_BOOK_ENTRIES >= 2) { ?>
                <div class="buttonRow forward"><?php
                    echo '<a href="' . zen_href_link(
                            FILENAME_CHECKOUT_PAYMENT_ADDRESS,
                            '',
                            'SSL'
                        ) . '">' . zen_image_button(
                             BUTTON_IMAGE_CHANGE_ADDRESS,
                             BUTTON_CHANGE_ADDRESS_ALT
                         ) . '</a>'; ?></div>
                <?php
            } ?>
            <address><?php
                echo zen_address_label($_SESSION['customer_id'], $_SESSION['billto'], true, ' ', '<br />'); ?></address>
        </div>

        <div class="floatingBox important forward"><?php
            echo TEXT_SELECTED_BILLING_DESTINATION; ?></div>
        <br class="clearBoth">
        <br>
        <?php
    }
    ?>

    <fieldset id="checkoutOrderTotals">
        <legend id="checkoutPaymentHeadingTotal"><?php
            echo TEXT_YOUR_TOTAL; ?></legend>
        <?php
        if (MODULE_ORDER_TOTAL_INSTALLED) {
            $order_totals = $order_total_modules->process();
            ?>
            <?php
            $order_total_modules->output(); ?>
            <?php
        }
        ?>
    </fieldset>

    <?php
    $selection = $order_total_modules->credit_selection();
    if (!empty($selection)) {
        for ($i = 0, $n = sizeof($selection); $i < $n; $i++) {
            if (isset($_GET['credit_class_error_code']) &&
                ($_GET['credit_class_error_code'] == (isset($selection[$i]['id']))
                    ? $selection[$i]['id'] : 0)) {
                ?>
                <div class="messageStackError"><?php
                    echo zen_output_string_protected($_GET['credit_class_error']); ?></div>

                <?php
            }
            for (
                $j = 0, $n2 = (isset($selection[$i]['fields']) ? sizeof(
                $selection[$i]['fields']
            ) : 0); $j < $n2; $j++
            ) {
                ?>
                <fieldset>
                    <legend><?php
                        echo $selection[$i]['module']; ?></legend>
                    <?php
                    echo $selection[$i]['redeem_instructions']; ?>
                    <div class="gvBal larger"><?php
                        echo (isset($selection[$i]['checkbox'])) ? $selection[$i]['checkbox'] : ''; ?></div>
                    <label class="inputLabel"<?php
                    echo ($selection[$i]['fields'][$j]['tag'])
                        ? ' for="' . $selection[$i]['fields'][$j]['tag'] . '"' : ''; ?>>
                        <?php
                        echo $selection[$i]['fields'][$j]['title']; ?></label>
                    <?php
                    echo $selection[$i]['fields'][$j]['field']; ?>
                </fieldset>
                <?php
            }
        }
        ?>

        <?php
    }
    ?>
    <input type="hidden" name="payment" value="<?php
    echo $_SESSION['payment']; ?>"/>

    <fieldset>
        <legend><?php
            echo TABLE_HEADING_COMMENTS; ?></legend>
        <?php
        echo zen_draw_textarea_field(
            'comments',
            '45',
            '3',
            (isset($comments) ? $comments : ''),
            'aria-label="' . TABLE_HEADING_COMMENTS . '"'
        ); ?>
    </fieldset>

    <?php
    if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
        ?>
        <fieldset>
            <legend><?php
                echo TABLE_HEADING_CONDITIONS; ?></legend>
            <div><?php
                echo TEXT_CONDITIONS_DESCRIPTION; ?></div>
            <?php
            echo zen_draw_checkbox_field('conditions', '1', false, 'id="conditions"'); ?>
            <label class="checkboxLabel" for="conditions"><?php
                echo TEXT_CONDITIONS_CONFIRM; ?></label>
        </fieldset>
        <?php
    }
    ?>

    <div class="buttonRow forward" id="paymentSubmit"><?php
        echo zen_image_submit(
            BUTTON_IMAGE_CONTINUE_CHECKOUT,
            BUTTON_CONTINUE_ALT,
            'onclick="submitFunction(' . zen_user_has_gv_account(
                $_SESSION['customer_id']
            ) . ',' . $order->info['total'] . ')"'
        ); ?></div>

    <div class="buttonRow back"><?php
        echo TITLE_CONTINUE_CHECKOUT_PROCEDURE . '<br />' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></div>

    </form>
</div>
