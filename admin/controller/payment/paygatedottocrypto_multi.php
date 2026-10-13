<?php
namespace Opencart\Admin\Controller\Extension\Paygatedottocrypto\Payment;
use Opencart\System\Library\Extension\Paygatedottocrypto\Paygate;
/**
 * Class PaygatedottocryptoMulti
 *
 * Settings for crypto payments in Multicoin mode (PayGate hosted checkout page). One payout wallet
 * per network family; install(), uninstall() and the order tab are shared with Paygatedottocrypto.
 *
 * @package Opencart\Admin\Controller\Extension\Paygatedottocrypto\Payment
 */
class PaygatedottocryptoMulti extends Paygatedottocrypto {
	/**
	 * Payout wallet setting per network family accepted by crypto/multi-hosted-wallet.php.
	 */
	private const WALLETS = ['evm', 'btc', 'bitcoincash', 'ltc', 'doge', 'solana', 'trc20', 'xmr', 'zec', 'ton'];

	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto_multi');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/paygatedottocrypto/payment/paygatedottocrypto_multi', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/paygatedottocrypto/payment/paygatedottocrypto_multi.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$title = $this->config->get('payment_paygatedottocrypto_multi_title');

		$data['payment_paygatedottocrypto_multi_title'] = $title !== '' ? $title : $this->language->get('text_default_title');

		foreach (['description', 'checkout_domain', 'api_domain', 'logo', 'background_color', 'theme_color', 'button_color'] as $key) {
			$data['payment_paygatedottocrypto_multi_' . $key] = (string)$this->config->get('payment_paygatedottocrypto_multi_' . $key);
		}

		$data['wallets'] = [];

		foreach (self::WALLETS as $family) {
			$data['wallets'][] = [
				'family' => $family,
				'label'  => $this->language->get('entry_wallet_' . $family),
				'value'  => (string)$this->config->get('payment_paygatedottocrypto_multi_wallet_' . $family)
			];
		}

		$data['payment_paygatedottocrypto_multi_tolerance'] = (string)($this->config->get('payment_paygatedottocrypto_multi_tolerance') ?: '0.99');
		$data['payment_paygatedottocrypto_multi_fees'] = (int)$this->config->get('payment_paygatedottocrypto_multi_fees');
		$data['payment_paygatedottocrypto_multi_status'] = (int)$this->config->get('payment_paygatedottocrypto_multi_status');
		$data['payment_paygatedottocrypto_multi_sort_order'] = $this->config->get('payment_paygatedottocrypto_multi_sort_order');
		$data['payment_paygatedottocrypto_multi_geo_zone_id'] = (int)$this->config->get('payment_paygatedottocrypto_multi_geo_zone_id');

		foreach (['order_status_id' => 1, 'paid_status_id' => 2, 'failed_status_id' => 10] as $key => $default) {
			$value = $this->config->get('payment_paygatedottocrypto_multi_' . $key);

			$data['payment_paygatedottocrypto_multi_' . $key] = $value !== '' ? (int)$value : $default;
		}

		$data['tolerances'] = $this->tolerances();

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/paygatedottocrypto/payment/paygatedottocrypto_multi', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto_multi');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/paygatedottocrypto/payment/paygatedottocrypto_multi')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;
		$settings = [];

		foreach (['checkout_domain', 'api_domain'] as $key) {
			$value = trim((string)($post['payment_paygatedottocrypto_multi_' . $key] ?? ''));

			if ($value !== '' && Paygate::normaliseHost($value, '') === '') {
				$json['error'][$key] = $this->language->get('error_domain');
			}

			$settings['payment_paygatedottocrypto_multi_' . $key] = $value === '' ? '' : Paygate::normaliseHost($value, '');
		}

		$has_wallet = false;

		foreach (self::WALLETS as $family) {
			$wallet = trim(html_entity_decode((string)($post['payment_paygatedottocrypto_multi_wallet_' . $family] ?? ''), ENT_QUOTES, 'UTF-8'));

			if ($wallet !== '' && !Paygate::isAddress($wallet)) {
				$json['error']['wallet_' . $family] = $this->language->get('error_wallet_format');
			}

			if ($wallet !== '') {
				$has_wallet = true;
			}

			$settings['payment_paygatedottocrypto_multi_wallet_' . $family] = $wallet;
		}

		if (!empty($post['payment_paygatedottocrypto_multi_status']) && !$has_wallet) {
			$json['error']['warning'] = $this->language->get('error_no_wallet');
		}

		$logo = trim(html_entity_decode((string)($post['payment_paygatedottocrypto_multi_logo'] ?? ''), ENT_QUOTES, 'UTF-8'));

		if ($logo !== '' && !preg_match('#^https://[^\s"<>]+$#i', $logo)) {
			$json['error']['logo'] = $this->language->get('error_logo');
		}

		foreach (['background_color', 'theme_color', 'button_color'] as $key) {
			$color = trim((string)($post['payment_paygatedottocrypto_multi_' . $key] ?? ''));

			if ($color !== '' && !preg_match('/^(#[0-9A-Fa-f]{3,8}|[A-Za-z]{3,20})$/', $color)) {
				$json['error'][str_replace('_', '-', $key)] = $this->language->get('error_color');
			}

			$settings['payment_paygatedottocrypto_multi_' . $key] = $color;
		}

		$tolerance = (string)($post['payment_paygatedottocrypto_multi_tolerance'] ?? '0.99');

		if (!isset($this->tolerances()[$tolerance])) {
			$tolerance = '0.99';
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$settings += [
				'payment_paygatedottocrypto_multi_title'            => (string)($post['payment_paygatedottocrypto_multi_title'] ?? ''),
				'payment_paygatedottocrypto_multi_description'      => (string)($post['payment_paygatedottocrypto_multi_description'] ?? ''),
				'payment_paygatedottocrypto_multi_logo'             => $logo,
				'payment_paygatedottocrypto_multi_tolerance'        => $tolerance,
				'payment_paygatedottocrypto_multi_fees'             => !empty($post['payment_paygatedottocrypto_multi_fees']) ? 1 : 0,
				'payment_paygatedottocrypto_multi_order_status_id'  => (int)($post['payment_paygatedottocrypto_multi_order_status_id'] ?? 1),
				'payment_paygatedottocrypto_multi_paid_status_id'   => (int)($post['payment_paygatedottocrypto_multi_paid_status_id'] ?? 2),
				'payment_paygatedottocrypto_multi_failed_status_id' => (int)($post['payment_paygatedottocrypto_multi_failed_status_id'] ?? 10),
				'payment_paygatedottocrypto_multi_geo_zone_id'      => (int)($post['payment_paygatedottocrypto_multi_geo_zone_id'] ?? 0),
				'payment_paygatedottocrypto_multi_status'           => !empty($post['payment_paygatedottocrypto_multi_status']) ? 1 : 0,
				'payment_paygatedottocrypto_multi_sort_order'       => (int)($post['payment_paygatedottocrypto_multi_sort_order'] ?? 0)
			];

			$this->model_setting_setting->editSetting('payment_paygatedottocrypto_multi', $settings);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return string
	 */
	protected function eventCode(): string {
		return 'paygatedottocrypto_multi';
	}
}
