<?php
// Heading
$_['heading_title']        = 'PayGate.to Crypto (Individual Coins)';

// Text
$_['text_extension']       = 'Extensions';
$_['text_success']         = 'Success: You have modified PayGate.to Crypto (Individual Coins) payment module!';
$_['text_edit']            = 'Edit PayGate.to Crypto (Individual Coins)';
$_['text_intro']           = 'Accept cryptocurrencies with instant payouts to your own wallets and without KYC. The customer picks a coin at checkout and the QR code and payment address are shown on your own store. Tick each coin you want to accept and enter its payout wallet.';
$_['text_default_title']   = 'Cryptocurrency';
$_['text_mode_coin']       = 'Individual coin (QR code on your store)';
$_['text_mode_multi']      = 'Multicoin (hosted checkout page)';
$_['text_status_pending']  = 'Awaiting payment';
$_['text_status_paid']     = 'Paid';
$_['text_status_failed']   = 'Underpaid / failed';

// Entry
$_['entry_title']          = 'Checkout Title';
$_['entry_description']    = 'Description';
$_['entry_api_domain']     = 'API Domain';
$_['entry_tolerance']      = 'Underpaid Tolerance';
$_['entry_fees']           = 'Customer Pays Blockchain Fees';
$_['entry_coins']          = 'Accepted Coins';
$_['entry_order_status']   = 'Order Status (awaiting payment)';
$_['entry_paid_status']    = 'Order Status (paid)';
$_['entry_failed_status']  = 'Order Status (underpaid)';
$_['entry_geo_zone']       = 'Geo Zone';
$_['entry_status']         = 'Status';
$_['entry_sort_order']     = 'Sort Order';

// Help
$_['help_api_domain']      = 'Your dedicated API endpoint from paygate.to (get it at paygate.to/white-label-api-custom-domain-guide/#dedicated-endpoint). Leave empty to use the shared api.paygate.to.';
$_['help_tolerance']       = 'Accept a payment that is slightly below the order total. Crypto rates move, so 1% or more is recommended.';
$_['help_fees']            = 'Add the estimated blockchain fee to the amount the customer must send.';
$_['help_coins']           = 'The coin list and icons are loaded live from PayGate. Tick a coin and enter the wallet that should receive its payouts. Coins without a wallet are not offered at checkout.';
$_['help_order_status']    = 'Set when the customer confirms the order and the payment address is created.';

// Column
$_['column_enable']        = 'Enable';
$_['column_coin']          = 'Coin';
$_['column_wallet']        = 'Payout Wallet Address';
$_['column_mode']          = 'Method';
$_['column_status']        = 'Payment Status';
$_['column_address']       = 'Payment Address';
$_['column_amount_coin']   = 'Amount Requested';
$_['column_amount']        = 'Order Total';
$_['column_paid']          = 'Last Payment';
$_['column_received']      = 'Total Received';
$_['column_txids']         = 'Payment TXIDs';
$_['column_txid_in']       = 'TXID (customer payment)';
$_['column_txid_out']      = 'TXID (payout to you)';
$_['column_ipn_token']     = 'IPN Token (for PayGate support)';
$_['column_date']          = 'Last Update';

// Error
$_['error_permission']     = 'Warning: You do not have permission to modify PayGate.to Crypto (Individual Coins)!';
$_['error_api_domain']     = 'Enter a domain name only, for example api-yourshop-1a2b3c4d5e6f7a8b9c0d1e2f.paygate.to';
$_['error_wallet_format']  = 'Wallet address looks invalid.';
$_['error_no_coin']        = 'Warning: Enable at least one coin and enter its payout wallet address, otherwise no cryptocurrency can be offered at checkout.';
$_['error_coin_list']      = 'Could not load the coin list from PayGate right now. Please reload this page in a moment, or check your server\'s outbound HTTPS connection and the API Domain above. Coins you already set up are shown below and kept when you save.';
