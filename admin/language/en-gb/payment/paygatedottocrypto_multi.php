<?php
// Heading
$_['heading_title']          = 'PayGate.to Crypto (Multicoin)';

// Text
$_['text_extension']         = 'Extensions';
$_['text_success']           = 'Success: You have modified PayGate.to Crypto (Multicoin) payment module!';
$_['text_edit']              = 'Edit PayGate.to Crypto (Multicoin)';
$_['text_intro']             = 'One payment method for all supported coins: the customer picks a coin on the PayGate hosted checkout page and pays there. Payouts go instantly to your own wallets, without KYC. Fill in a wallet for each network you want to accept.';
$_['text_default_title']     = 'Cryptocurrency (all coins)';
$_['text_branding']          = 'Hosted page branding (optional)';
$_['text_wallets']           = 'Payout wallets';

// Entry
$_['entry_title']            = 'Checkout Title';
$_['entry_description']      = 'Description';
$_['entry_checkout_domain']  = 'Custom Domain';
$_['entry_api_domain']       = 'API Domain';
$_['entry_wallet_evm']       = 'EVM Wallet (ETH, ERC20, BEP20, Polygon, Arbitrum, Base, Optimism, Avalanche-C)';
$_['entry_wallet_btc']       = 'Bitcoin Wallet (BTC)';
$_['entry_wallet_bitcoincash'] = 'Bitcoin Cash Wallet (BCH)';
$_['entry_wallet_ltc']       = 'Litecoin Wallet (LTC)';
$_['entry_wallet_doge']      = 'Dogecoin Wallet (DOGE)';
$_['entry_wallet_solana']    = 'Solana Wallet (SOL and Solana tokens)';
$_['entry_wallet_trc20']     = 'Tron Wallet (TRX, TRC20 USDT)';
$_['entry_wallet_xmr']       = 'Monero Wallet (XMR)';
$_['entry_wallet_zec']       = 'Zcash Wallet (ZEC)';
$_['entry_wallet_ton']       = 'TON Wallet (Toncoin, TON USDT)';
$_['entry_tolerance']        = 'Underpaid Tolerance';
$_['entry_fees']             = 'Customer Pays Blockchain Fees';
$_['entry_logo']             = 'Logo URL';
$_['entry_background_color'] = 'Background Color';
$_['entry_theme_color']      = 'Theme Color';
$_['entry_button_color']     = 'Button Color';
$_['entry_order_status']     = 'Order Status (awaiting payment)';
$_['entry_paid_status']      = 'Order Status (paid)';
$_['entry_failed_status']    = 'Order Status (underpaid)';
$_['entry_geo_zone']         = 'Geo Zone';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';

// Help
$_['help_checkout_domain']   = 'Domain of the hosted checkout page. Leave empty to use checkout.paygate.to, or use your own domain from the custom domain guide at paygate.to/white-label-api-custom-domain-guide/';
$_['help_api_domain']        = 'Your dedicated API endpoint from paygate.to (get it at paygate.to/white-label-api-custom-domain-guide/#dedicated-endpoint). Leave empty to use the shared api.paygate.to.';
$_['help_wallets']           = 'Fill in at least one wallet. Networks without a wallet are not offered on the hosted page.';
$_['help_tolerance']         = 'Accept a payment that is slightly below the order total. Crypto rates move, so 1% or more is recommended.';
$_['help_fees']              = 'Add the estimated blockchain fee to the amount the customer must send.';
$_['help_logo']              = 'Your logo on the hosted checkout page (https:// image URL).';
$_['help_color']             = 'HEX color such as #1d4ed8, or a CSS color name.';
$_['help_order_status']      = 'Set when the customer confirms the order and is sent to the hosted checkout page.';

// Error
$_['error_permission']       = 'Warning: You do not have permission to modify PayGate.to Crypto (Multicoin)!';
$_['error_domain']           = 'Enter a domain name only, for example checkout.yourdomain.com';
$_['error_wallet_format']    = 'Wallet address looks invalid.';
$_['error_no_wallet']        = 'Warning: Enter at least one payout wallet, otherwise no cryptocurrency can be offered at checkout.';
$_['error_logo']             = 'Enter a full https:// image URL.';
$_['error_color']            = 'Enter a HEX color such as #1d4ed8 or a CSS color name.';
