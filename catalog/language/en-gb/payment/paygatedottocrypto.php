<?php
// Heading
$_['heading_title']           = 'Cryptocurrency';

// Text
$_['text_select_coin']        = 'Select the coin you want to pay with:';
$_['text_pay_title']          = 'Please complete your payment';
$_['text_send']               = 'Please send %s %s to the following address:';
$_['text_copy']               = 'Copy';
$_['text_copied']             = 'Copied';
$_['text_waiting']            = 'Waiting for payment';
$_['text_paid']               = 'Payment received. Thank you!';
$_['text_failed']             = 'Payment failed: you may have sent an incorrect amount or token. Please contact support.';
$_['text_underpaid']          = 'We received %s %s, which is less than the amount due. Please send the remaining %s %s to the same address.';
$_['text_status_error']       = 'Could not check the payment status. Please refresh the page.';
$_['text_note']               = 'Send the exact amount of the selected coin on the right network. This amount is guaranteed for 3 hours. Your order is updated automatically once the payment is confirmed on the blockchain.';
$_['text_history_awaiting']   = 'Awaiting crypto payment: send %s to %s. Payment page: %s';
$_['text_history_paid']       = '[Payment completed] Customer sent %s (total received ~%.2f %s). TXID: %s';
$_['text_history_underpaid']  = '[Payment underpaid] Received %s (~%.2f %s); total received ~%.2f %s, required minimum: %.2f %s. TXID: %s. The customer can send the rest to the same address.';
$_['text_history_other_coin'] = '[Notice] Customer paid in %s instead of %s; verifying the received value.';
$_['text_history_kept']       = '[Notice] The order status was left as set by the store.';

// Button
$_['button_confirm']          = 'Confirm Order';
$_['button_continue']         = 'Continue';

// Error
$_['error_order']             = 'Order not found. Please refresh the page and try again.';
$_['error_payment_method']    = 'Payment method is incorrect!';
$_['error_paid']              = 'This order is already paid.';
$_['error_started']           = 'A payment was already received for this order. Please contact the store.';
$_['error_coin']              = 'Please select a valid cryptocurrency to pay with.';
$_['error_convert']           = 'Payment could not be processed, please try again (unsupported store currency).';
$_['error_fees']              = 'Failed to get estimated blockchain fees, please try again.';
$_['error_minimum_fetch']     = 'Payment could not be processed, please try again (failed to fetch the coin minimum).';
$_['error_minimum']           = 'The order total is below the minimum allowed for the selected coin. Please choose another coin.';
$_['error_wallet']            = 'Payment could not be processed due to incorrect payout wallet settings, please contact the store.';
$_['error_qr']                = 'Unable to generate the payment QR code, please try again.';
