<?php
namespace Opencart\Catalog\Controller\Extension\Paygatedottocrypto\Payment;
use Opencart\System\Library\Extension\Paygatedottocrypto\Paygate;
/**
 * Class PaygatedottocryptoMulti
 *
 * Crypto payments, Multicoin: one payment method, the customer picks any enabled coin on the PayGate
 * hosted checkout page (optionally on your own custom domain, with your logo and colours). Payment is
 * confirmed by the shared, signature-verified callback in Paygatedottocrypto::callback().
 *
 * @package Opencart\Catalog\Controller\Extension\Paygatedottocrypto\Payment
 */
class PaygatedottocryptoMulti extends \Opencart\System\Engine\Controller {
	/**
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto_multi');

		$data['description'] = nl2br(htmlspecialchars(html_entity_decode((string)$this->config->get('payment_paygatedottocrypto_multi_description'), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'));
		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/paygatedottocrypto/payment/paygatedottocrypto_multi', $data);
	}

	/**
	 * Create the hosted multi-coin session and send the customer to the hosted checkout page.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto_multi');

		$this->load->model('checkout/order');
		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto_multi');

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

		if (!isset($this->session->data['payment_method']['code']) || $this->session->data['payment_method']['code'] != 'paygatedottocrypto_multi.paygatedottocrypto_multi') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		$wallets = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto_multi->getWallets();

		if (!$json && !$wallets) {
			$json['error'] = $this->language->get('error_wallet');
		}

		if (!$json) {
			$order_id = (int)$order_info['order_id'];
			$fees_on = (bool)$this->config->get('payment_paygatedottocrypto_multi_fees');
			$existing = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto_multi->getPayment($order_id);

			if ($existing && $existing['status'] == 'paid') {
				// Never start a new payment for a paid order.
				$json['error'] = $this->language->get('error_paid');
			} elseif ($existing && $existing['mode'] == 'multi' && $existing['payment_token'] !== '' && ($existing['status'] == 'pending' || $existing['txids'] !== '')) {
				// Session already created for this order: send the customer back to it.
				$json['redirect'] = $this->hostedUrl((string)$existing['payment_token'], (bool)$existing['fees']);
			} elseif ($existing && ($existing['txids'] !== '' || $existing['status'] != 'pending')) {
				$json['error'] = $this->language->get('error_started');
			} else {
				$currency = (string)$order_info['currency_code'];
				$amount = round((float)$this->currency->format((float)$order_info['total'], $currency, (float)$order_info['currency_value'], false), 2);
				$nonce = !empty($existing['nonce']) ? (string)$existing['nonce'] : Paygate::token();
				$status_key = !empty($existing['status_key']) ? (string)$existing['status_key'] : Paygate::token();
				$callback_url = Paygate::callbackUrl((string)$this->config->get('config_url'), $order_id, $nonce);

				$api = new Paygate((string)$this->config->get('payment_paygatedottocrypto_multi_api_domain'));

				$response = $api->postJson('multi-hosted-wallet.php', $wallets + [
					'fiat_amount'   => $amount,
					'fiat_currency' => $currency,
					'callback'      => $callback_url
				]);

				$token = isset($response['payment_token']) && is_string($response['payment_token']) ? $response['payment_token'] : '';

				if ($token === '' || !preg_match('/^[A-Za-z0-9%._~+\/=-]+$/', $token)) {
					$json['error'] = $this->language->get('error_session');
				} else {
					$tolerance = (float)$this->config->get('payment_paygatedottocrypto_multi_tolerance');

					$saved = $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto_multi->savePayment($order_id, [
						'mode'          => 'multi',
						'payment_token' => $token,
						'ipn_token'     => (string)($response['ipn_token'] ?? ''),
						'amount'        => $amount,
						'currency_code' => $currency,
						'tolerance'     => ($tolerance >= 0.9 && $tolerance <= 1) ? $tolerance : 0.99,
						'fees'          => $fees_on ? 1 : 0,
						'api_host'      => $api->getHost(),
						'nonce'         => $nonce,
						'status_key'    => $status_key,
						'callback_url'  => $callback_url
					]);

					if (!$saved) {
						// A payment arrived for this order in the meantime.
						$json['error'] = $this->language->get('error_started');
					} else {
						$hosted_url = $this->hostedUrl($token, $fees_on);

						$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_paygatedottocrypto_multi_order_status_id') ?: 1, sprintf($this->language->get('text_history_awaiting'), htmlspecialchars($hosted_url, ENT_QUOTES, 'UTF-8')), true);

						$json['redirect'] = $hosted_url;
					}
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
	 * Empty the cart once the order is placed, as checkout/success does, so the order cannot be
	 * changed after its payment session was created.
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
	 * Hosted checkout URL, on your custom checkout domain when one is set.
	 *
	 * @param string $token  payment_token exactly as returned by PayGate (already URL-encoded)
	 * @param bool   $fees_on
	 *
	 * @return string
	 */
	private function hostedUrl(string $token, bool $fees_on): string {
		$domain = Paygate::normaliseHost((string)$this->config->get('payment_paygatedottocrypto_multi_checkout_domain'), Paygate::DEFAULT_CHECKOUT_HOST);

		$url = 'https://' . $domain . '/crypto/hosted.php?payment_token=' . $token . '&add_fees=' . ($fees_on ? '1' : '0');

		foreach (['logo' => 'logo', 'background' => 'background_color', 'theme' => 'theme_color', 'button' => 'button_color'] as $param => $setting) {
			$value = trim(html_entity_decode((string)$this->config->get('payment_paygatedottocrypto_multi_' . $setting), ENT_QUOTES, 'UTF-8'));

			if ($value !== '') {
				$url .= '&' . $param . '=' . urlencode($value);
			}
		}

		return $url;
	}
}
