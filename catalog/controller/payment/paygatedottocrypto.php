<?php
namespace Opencart\Catalog\Controller\Extension\Paygatedottocrypto\Payment;
use Opencart\System\Library\Extension\Paygatedottocrypto\Paygate;
/**
 * Class Paygatedottocrypto
 *
 * Crypto payments, individual coins. The customer picks a coin at checkout; a unique pay-in
 * address and QR code are created through PayGate and shown on your own store. Crypto sent to that
 * address is forwarded instantly to your payout wallet, and PayGate's signed callback marks the
 * order paid. The callback() method also confirms Multicoin (hosted page) orders.
 *
 * @package Opencart\Catalog\Controller\Extension\Paygatedottocrypto\Payment
 */
class Paygatedottocrypto extends \Opencart\System\Engine\Controller {
	/**
	 * A quote younger than this (seconds) is reused when the customer confirms again with the same coin.
	 */
	private const QUOTE_REUSE = 900;

	/**
	 * The coin amount shown to the customer is honoured for this long (seconds), whatever the rate does.
	 */
	private const RATE_LOCK = 10800;

	/**
	 * Payment method block at checkout: coin selector + confirm button.
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$data['coins'] = [];

		foreach ($this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getEnabledCoins() as $coin) {
			$data['coins'][] = [
				'id'    => $coin['id'],
				'label' => Paygate::coinLabel($coin),
				'logo'  => $coin['logo']
			];
		}

		$data['description'] = nl2br(htmlspecialchars(html_entity_decode((string)$this->config->get('payment_paygatedottocrypto_description'), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'));
		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/paygatedottocrypto/payment/paygatedottocrypto', $data);
	}

	/**
	 * Create the pay-in address for the selected coin and send the customer to the payment page.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$this->load->model('checkout/order');
		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$json = [];
		$order_info = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');
		} else {
			$order_info = $this->model_checkout_order->getOrder((int)$this->session->data['order_id']);

			if (!$order_info) {
				$json['error'] = $this->language->get('error_order');
			}
		}

		if (!isset($this->session->data['payment_method']['code']) || $this->session->data['payment_method']['code'] != 'paygatedottocrypto.paygatedottocrypto') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		$coins = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getEnabledCoins();
		$coin_id = isset($this->request->post['coin']) ? (string)$this->request->post['coin'] : '';

		if (!$json && !isset($coins[$coin_id])) {
			$json['error'] = $this->language->get('error_coin');
		}

		if (!$json) {
			$order_id = (int)$order_info['order_id'];
			$coin = $coins[$coin_id];
			$existing = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getPayment($order_id);

			if ($existing && $existing['status'] == 'paid') {
				// Never quote a paid order again.
				$json['error'] = $this->language->get('error_paid');
			} elseif ($existing && ($existing['txids'] !== '' || $existing['status'] != 'pending')) {
				// Something was already received: the customer completes the payment on the same address.
				if ($existing['mode'] == 'coin') {
					$json['redirect'] = $this->payUrl($order_id, (string)$existing['status_key']);
				} else {
					$json['error'] = $this->language->get('error_started');
				}
			} elseif ($existing && $existing['mode'] == 'coin' && $existing['coin'] == $coin_id && $existing['address_in'] !== '' && (int)$existing['quote_age'] < self::QUOTE_REUSE) {
				// Same order, same coin, recent quote: reuse it (no new address).
				$json['redirect'] = $this->payUrl($order_id, (string)$existing['status_key']);
			} else {
				$result = $this->createAddress($order_info, $coin, $existing);

				if (isset($result['error'])) {
					$json['error'] = $result['error'];
				} else {
					$pay_url = $this->payUrl($order_id, $result['status_key']);

					$comment = sprintf($this->language->get('text_history_awaiting'), htmlspecialchars($result['display_total'] . ' ' . Paygate::coinLabel($coin), ENT_QUOTES, 'UTF-8'), htmlspecialchars($result['address_in'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($pay_url, ENT_QUOTES, 'UTF-8'));

					$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_paygatedottocrypto_order_status_id') ?: 1, $comment, true);

					$json['redirect'] = $pay_url;
				}
			}
		}

		if (isset($json['redirect'])) {
			$this->clearCheckout();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * convert -> optional blockchain fees -> minimum -> wallet -> QR code, then store the record.
	 *
	 * @param array<string, mixed>  $order_info
	 * @param array<string, string> $coin
	 * @param array<string, mixed>  $existing  previous record of this order, if any
	 *
	 * @return array<string, string>
	 */
	private function createAddress(array $order_info, array $coin, array $existing): array {
		$api = new Paygate((string)$this->config->get('payment_paygatedottocrypto_api_domain'));
		$order_id = (int)$order_info['order_id'];
		$currency = (string)$order_info['currency_code'];
		$amount = round((float)$this->currency->format((float)$order_info['total'], $currency, (float)$order_info['currency_value'], false), 2);
		$fees_on = (bool)$this->config->get('payment_paygatedottocrypto_fees');
		$path = $coin['path'];

		// 1) Order total in the selected coin.
		$convert = $api->get($path . '/convert.php', ['value' => number_format($amount, 2, '.', ''), 'from' => strtolower($currency)]);

		if (!isset($convert['value_coin']) || !is_numeric($convert['value_coin'])) {
			return ['error' => $this->language->get('error_convert')];
		}

		$payin = (float)$convert['value_coin'];

		// 2) Optionally add the estimated blockchain fee.
		if ($fees_on) {
			$fees = $api->get($path . '/fees.php');

			if (!isset($fees['estimated_cost_currency']['USD']) || !is_numeric($fees['estimated_cost_currency']['USD'])) {
				return ['error' => $this->language->get('error_fees')];
			}

			$fee_coin = $api->get($path . '/convert.php', ['value' => (string)$fees['estimated_cost_currency']['USD'], 'from' => 'usd']);

			if (!isset($fee_coin['value_coin']) || !is_numeric($fee_coin['value_coin'])) {
				return ['error' => $this->language->get('error_fees')];
			}

			$payin += (float)$fee_coin['value_coin'];
		}

		// 3) Coin minimum: anything below it would be stuck at the pay-in address.
		$info = $api->get($path . '/info.php');

		if (!isset($info['minimum']) || !is_numeric($info['minimum'])) {
			return ['error' => $this->language->get('error_minimum_fetch')];
		}

		if ($payin < (float)$info['minimum']) {
			return ['error' => $this->language->get('error_minimum')];
		}

		// 4) Unique pay-in address bound to this order's callback. The nonce and status key are kept
		// for the life of the order, so every address created for it shares one callback secret.
		$nonce = !empty($existing['nonce']) ? (string)$existing['nonce'] : Paygate::token();
		$status_key = !empty($existing['status_key']) ? (string)$existing['status_key'] : Paygate::token();
		$callback_url = Paygate::callbackUrl((string)$this->config->get('config_url'), $order_id, $nonce);

		$wallet = $api->get($path . '/wallet.php', ['address' => $coin['wallet'], 'callback' => $callback_url]);

		if (!isset($wallet['address_in']) || !is_string($wallet['address_in']) || !Paygate::isAddress($wallet['address_in'])) {
			return ['error' => $this->language->get('error_wallet')];
		}

		// 5) QR code of the pay-in address.
		$qr = $api->get($path . '/qrcode.php', ['address' => $wallet['address_in']]);

		if (!isset($qr['qr_code']) || !is_string($qr['qr_code']) || !preg_match('#^[A-Za-z0-9+/=]+$#', $qr['qr_code'])) {
			return ['error' => $this->language->get('error_qr')];
		}

		$display_total = rtrim(rtrim(number_format($payin, 8, '.', ''), '0'), '.');

		$saved = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->savePayment($order_id, [
			'mode'          => 'coin',
			'coin'          => $coin['id'],
			'coin_label'    => Paygate::coinLabel($coin),
			'address_in'    => $wallet['address_in'],
			'ipn_token'     => (string)($wallet['ipn_token'] ?? ''),
			'display_total' => $display_total,
			'qr_code'       => $qr['qr_code'],
			'amount'        => $amount,
			'currency_code' => $currency,
			'tolerance'     => $this->tolerance('payment_paygatedottocrypto_tolerance'),
			'fees'          => $fees_on ? 1 : 0,
			'api_host'      => $api->getHost(),
			'nonce'         => $nonce,
			'status_key'    => $status_key,
			'callback_url'  => $callback_url
		]);

		if (!$saved) {
			// A payment arrived for this order in the meantime.
			return ['error' => $this->language->get('error_started')];
		}

		return ['address_in' => (string)$wallet['address_in'], 'display_total' => $display_total, 'status_key' => $status_key];
	}

	/**
	 * Payment page on your own store: QR code, amount and address, with live status.
	 *
	 * @return void
	 */
	public function pay(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$payment = $this->paymentFromRequest();

		if (!$payment || $payment['mode'] != 'coin') {
			$this->response->redirect($this->url->link('common/home', 'language=' . $this->config->get('config_language')));

			return;
		}

		$this->document->setTitle($this->language->get('text_pay_title'));

		$data['order_id'] = (int)$payment['order_id'];
		$data['coin_label'] = $payment['coin_label'];
		$data['address'] = $payment['address_in'];
		$data['display_total'] = $payment['display_total'];
		$data['qr_code'] = $payment['qr_code'];
		$data['status'] = $payment['status'];
		$data['message'] = $this->statusMessage($payment);
		$data['status_url'] = $this->url->link('extension/paygatedottocrypto/payment/paygatedottocrypto.status', 'order_id=' . (int)$payment['order_id'] . '&key=' . $payment['status_key'], true);
		$data['continue'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/paygatedottocrypto/payment/paygatedottocrypto_pay', $data));
	}

	/**
	 * Live payment status for the payment page (JSON).
	 *
	 * @return void
	 */
	public function status(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$payment = $this->paymentFromRequest();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->setOutput(json_encode(['status' => $payment ? (string)$payment['status'] : 'unknown', 'message' => $payment ? $this->statusMessage($payment) : '']));
	}

	/**
	 * Text for the status box of the payment page.
	 *
	 * @param array<string, mixed> $payment
	 *
	 * @return string
	 */
	private function statusMessage(array $payment): string {
		if ($payment['status'] == 'paid') {
			return $this->language->get('text_paid');
		}

		if ($payment['status'] != 'failed') {
			return $this->language->get('text_waiting');
		}

		$received = (float)$payment['received_coin'];
		$remaining = (float)$payment['display_total'] - $received;

		if ($payment['mode'] == 'coin' && $received > 0 && $remaining > 0) {
			return sprintf($this->language->get('text_underpaid'), $this->coinAmount($received), (string)$payment['coin_label'], $this->coinAmount($remaining), (string)$payment['coin_label']);
		}

		return $this->language->get('text_failed');
	}

	/**
	 * @param float $value
	 *
	 * @return string
	 */
	private function coinAmount(float $value): string {
		return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
	}

	/**
	 * Payment record for order_id + key from the query string, or [] when they don't match.
	 *
	 * @return array<string, mixed>
	 */
	private function paymentFromRequest(): array {
		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
		$key = isset($this->request->get['key']) ? (string)$this->request->get['key'] : '';

		if (!$order_id || $key === '') {
			return [];
		}

		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$payment = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getPayment($order_id);

		if (!$payment || !hash_equals((string)$payment['status_key'], $key)) {
			return [];
		}

		return $payment;
	}

	/**
	 * Payment confirmation from PayGate (individual coins and Multicoin).
	 *
	 * Order of checks: per-order nonce, then PayGate's RSA signature over the exact callback URL,
	 * and only then any payment value. Every parameter is read from the signed query string itself.
	 * Payments add up: the order is paid once the coin amount quoted to the customer has arrived
	 * within 3 hours (same coin), or once the total received, valued in the order currency, reaches
	 * the order total x tolerance (+ estimated blockchain fee when the customer pays fees).
	 * Answers plain "ok" once handled so PayGate stops retrying.
	 *
	 * @return void
	 */
	public function callback(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$this->load->model('checkout/order');
		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$params = Paygate::callbackParams();

		$order_id = isset($params['order_id']) && is_string($params['order_id']) ? (int)$params['order_id'] : 0;
		$nonce = isset($params['nonce']) && is_string($params['nonce']) ? $params['nonce'] : '';

		$payment = $order_id ? $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getPayment($order_id) : [];

		if (!$payment) {
			$this->plain(404, 'Unknown order');

			return;
		}

		if ($nonce === '' || !hash_equals((string)$payment['nonce'], $nonce)) {
			$this->plain(403, 'Invalid nonce');

			return;
		}

		$check = Paygate::verifyCallback((string)$payment['callback_url'], DIR_EXTENSION . 'paygatedottocrypto/system/library/keys/');

		if (!$check['verified']) {
			$this->log->write('PayGate crypto: rejected callback for order ' . $order_id . ' (' . $check['reason'] . '). No order status was changed.');

			$this->plain(403, 'Invalid callback signature');

			return;
		}

		// Unconfirmed (pending) transactions never change the order; the confirmed callback follows.
		if (isset($params['pending']) && $params['pending'] === '1') {
			$this->plain(200, 'ok');

			return;
		}

		$paid_coin = isset($params['coin']) && is_string($params['coin']) ? strtolower($params['coin']) : '';
		$value_coin = isset($params['value_coin']) && is_string($params['value_coin']) ? $params['value_coin'] : '';
		$txid_in = isset($params['txid_in']) && is_string($params['txid_in']) ? $params['txid_in'] : '';
		$txid_out = isset($params['txid_out']) && is_string($params['txid_out']) && Paygate::isTxid($params['txid_out']) ? $params['txid_out'] : '';

		if (!Paygate::isCoinId($paid_coin) || !preg_match('/^\d{1,20}(\.\d{1,18})?$/', $value_coin) || (float)$value_coin <= 0 || !Paygate::isTxid($txid_in)) {
			$this->plain(400, 'Invalid payment data');

			return;
		}

		// Already settled, or this exact transaction was already counted: nothing to do.
		if ($payment['status'] == 'paid' || in_array($txid_in, explode(',', (string)$payment['txids']), true)) {
			$this->plain(200, 'ok');

			return;
		}

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$this->plain(404, 'Unknown order');

			return;
		}

		$api = new Paygate((string)$payment['api_host']);
		$currency = (string)$payment['currency_code'];
		$path = Paygate::coinPath($paid_coin);

		$info = $api->get($path . '/info.php');

		if (!isset($info['prices'][$currency]) || !is_numeric($info['prices'][$currency])) {
			$this->plain(502, 'Could not value the payment, please retry');

			return;
		}

		$value_fiat = (float)$value_coin * (float)$info['prices'][$currency];
		$required = (float)$payment['amount'] * (float)$payment['tolerance'];

		if ((int)$payment['fees']) {
			$fees = $api->get($path . '/fees.php');

			if (!isset($fees['estimated_cost_currency'][$currency]) || !is_numeric($fees['estimated_cost_currency'][$currency])) {
				$this->plain(502, 'Could not value the payment, please retry');

				return;
			}

			$required += (float)$fees['estimated_cost_currency'][$currency];
		}

		$same_coin = $payment['mode'] == 'coin' && $paid_coin === (string)$payment['coin'];

		$added = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->addTransaction($order_id, [
			'paid_coin'  => $paid_coin,
			'value_coin' => $value_coin,
			'value_fiat' => $value_fiat,
			'same_coin'  => $same_coin,
			'txid_in'    => $txid_in,
			'txid_out'   => $txid_out
		]);

		if (!$added) {
			// Counted by a concurrent callback, or the order was paid meanwhile.
			$this->plain(200, 'ok');

			return;
		}

		$payment = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getPayment($order_id);

		$received = (float)$payment['received_total'];

		// Rate lock: the exact coin amount quoted to the customer is honoured for 3 hours.
		$locked = $same_coin && (int)$payment['quote_age'] <= self::RATE_LOCK && (float)$payment['received_coin'] + 0.000000005 >= (float)$payment['display_total'] * (float)$payment['tolerance'];

		$settings = $payment['mode'] == 'multi' ? 'payment_paygatedottocrypto_multi_' : 'payment_paygatedottocrypto_';
		$coin_text = htmlspecialchars($value_coin . ' ' . strtoupper($paid_coin), ENT_QUOTES, 'UTF-8');
		$txid_text = htmlspecialchars($txid_in, ENT_QUOTES, 'UTF-8');
		$notes = '';

		if ($payment['mode'] == 'coin' && !$same_coin) {
			$notes .= sprintf($this->language->get('text_history_other_coin'), htmlspecialchars(strtoupper($paid_coin), ENT_QUOTES, 'UTF-8'), htmlspecialchars((string)$payment['coin_label'], ENT_QUOTES, 'UTF-8')) . ' ';
		}

		$paid_status_id = (int)$this->config->get($settings . 'paid_status_id') ?: 2;
		$failed_status_id = (int)$this->config->get($settings . 'failed_status_id') ?: 10;

		if ($locked || $received + 0.00001 >= $required) {
			if ($this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->setPaid($order_id)) {
				$comment = $notes . sprintf($this->language->get('text_history_paid'), $coin_text, $received, $currency, $txid_text);

				$this->addHistory($order_info, $settings, $paid_status_id, $failed_status_id, $comment, true);
			}
		} else {
			$this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->setUnderpaid($order_id);

			$comment = $notes . sprintf($this->language->get('text_history_underpaid'), $coin_text, $value_fiat, $currency, $received, $currency, $required, $currency, $txid_text);

			$this->addHistory($order_info, $settings, $failed_status_id, $failed_status_id, $comment, false);
		}

		$this->plain(200, 'ok');
	}

	/**
	 * Add the callback outcome to the order history.
	 *
	 * An order the merchant already moved on to another processing or complete status (Shipped,
	 * Complete, ...) keeps that status; the outcome is only added as a note.
	 *
	 * @param array<string, mixed> $order_info
	 * @param string               $settings   setting prefix of the payment method
	 * @param int                  $status_id  status for this outcome
	 * @param int                  $failed_status_id
	 * @param string               $comment
	 * @param bool                 $notify
	 *
	 * @return void
	 */
	private function addHistory(array $order_info, string $settings, int $status_id, int $failed_status_id, string $comment, bool $notify): void {
		$current = (int)$order_info['order_status_id'];
		$awaiting = (int)$this->config->get($settings . 'order_status_id') ?: 1;
		$merchant = array_diff(array_map('intval', array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'))), [$awaiting, $failed_status_id]);

		if ($current !== $status_id && in_array($current, $merchant, true)) {
			$status_id = $current;
			$comment .= ' ' . $this->language->get('text_history_kept');
		}

		$this->model_checkout_order->addHistory((int)$order_info['order_id'], $status_id, $comment, $notify);
	}

	/**
	 * Event handler for catalog/controller/common/maintenance/after.
	 *
	 * OpenCart answers every storefront URL with its maintenance page while maintenance mode is on.
	 * This lets PayGate's callback through so payments are still confirmed; callback() runs all of
	 * its usual checks. Any other page keeps the maintenance page.
	 *
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output
	 *
	 * @return void
	 */
	public function maintenance(string &$route, array &$args, mixed &$output): void {
		// Both payment methods register this event; the callback runs once.
		static $done = false;

		$requested = str_replace('|', '.', (string)($this->request->get['route'] ?? ''));

		if ($done || $requested !== 'extension/paygatedottocrypto/payment/paygatedottocrypto.callback') {
			return;
		}

		$done = true;

		$this->callback();
	}

	/**
	 * Empty the cart once the order is placed, as checkout/success does, so the order cannot be
	 * changed after its payment address was created.
	 *
	 * @return void
	 */
	private function clearCheckout(): void {
		$this->cart->clear();

		foreach (['order_id', 'payment_method', 'payment_methods', 'shipping_method', 'shipping_methods', 'comment', 'agree', 'coupon', 'reward', 'voucher', 'vouchers'] as $key) {
			unset($this->session->data[$key]);
		}
	}

	/**
	 * Underpaid tolerance setting as a multiplier between 0.90 and 1.
	 *
	 * @param string $key
	 *
	 * @return float
	 */
	private function tolerance(string $key): float {
		$tolerance = (float)$this->config->get($key);

		return ($tolerance >= 0.9 && $tolerance <= 1) ? $tolerance : 0.99;
	}

	/**
	 * @param int    $order_id
	 * @param string $key
	 *
	 * @return string
	 */
	private function payUrl(int $order_id, string $key): string {
		return $this->url->link('extension/paygatedottocrypto/payment/paygatedottocrypto.pay', 'language=' . $this->config->get('config_language') . '&order_id=' . $order_id . '&key=' . $key, true);
	}

	/**
	 * Plain-text answer for PayGate.
	 *
	 * @param int    $code
	 * @param string $text
	 *
	 * @return void
	 */
	private function plain(int $code, string $text): void {
		$names = [200 => 'OK', 400 => 'Bad Request', 403 => 'Forbidden', 404 => 'Not Found', 502 => 'Bad Gateway'];

		$this->response->addHeader('HTTP/1.1 ' . $code . ' ' . ($names[$code] ?? ''));
		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->setOutput($text);
	}
}
