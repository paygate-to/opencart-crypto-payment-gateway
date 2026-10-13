<?php
namespace Opencart\Admin\Controller\Extension\Paygatedottocrypto\Payment;
use Opencart\System\Library\Extension\Paygatedottocrypto\Paygate;
/**
 * Class Paygatedottocrypto
 *
 * Settings for crypto payments with individual coins. The coin list and icons are fetched live from
 * PayGate; tick a coin and enter its payout wallet to accept it.
 *
 * @package Opencart\Admin\Controller\Extension\Paygatedottocrypto\Payment
 */
class Paygatedottocrypto extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

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
			'href' => $this->url->link('extension/paygatedottocrypto/payment/paygatedottocrypto', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/paygatedottocrypto/payment/paygatedottocrypto.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$title = $this->config->get('payment_paygatedottocrypto_title');

		$data['payment_paygatedottocrypto_title'] = $title !== '' ? $title : $this->language->get('text_default_title');
		$data['payment_paygatedottocrypto_description'] = (string)$this->config->get('payment_paygatedottocrypto_description');
		$data['payment_paygatedottocrypto_api_domain'] = (string)$this->config->get('payment_paygatedottocrypto_api_domain');
		$data['payment_paygatedottocrypto_tolerance'] = (string)($this->config->get('payment_paygatedottocrypto_tolerance') ?: '0.99');
		$data['payment_paygatedottocrypto_fees'] = (int)$this->config->get('payment_paygatedottocrypto_fees');
		$data['payment_paygatedottocrypto_status'] = (int)$this->config->get('payment_paygatedottocrypto_status');
		$data['payment_paygatedottocrypto_sort_order'] = $this->config->get('payment_paygatedottocrypto_sort_order');
		$data['payment_paygatedottocrypto_geo_zone_id'] = (int)$this->config->get('payment_paygatedottocrypto_geo_zone_id');

		// Order statuses: awaiting payment, paid, underpaid (defaults: Pending, Processing, Failed).
		foreach (['order_status_id' => 1, 'paid_status_id' => 2, 'failed_status_id' => 10] as $key => $default) {
			$value = $this->config->get('payment_paygatedottocrypto_' . $key);

			$data['payment_paygatedottocrypto_' . $key] = $value !== '' ? (int)$value : $default;
		}

		$data['tolerances'] = $this->tolerances();

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		// Coin table: live coin list, pre-filled from the saved configuration.
		$saved = $this->config->get('payment_paygatedottocrypto_coins');
		$saved = is_array($saved) ? $saved : [];

		$map = $this->coinMap($data['payment_paygatedottocrypto_api_domain'], false);

		$data['coin_list_error'] = !$map;

		// Coin list unavailable: still show (and keep) the coins already configured.
		if (!$map) {
			foreach ($saved as $coin_id => $coin) {
				if (is_array($coin) && Paygate::isCoinId((string)$coin_id)) {
					$map[(string)$coin_id] = ['label' => (string)($coin['label'] ?? strtoupper((string)$coin_id)), 'chain' => (string)($coin['chain'] ?? ''), 'logo' => (string)($coin['logo'] ?? '')];
				}
			}
		}

		$data['coins'] = [];

		foreach ($map as $coin_id => $coin) {
			$data['coins'][] = [
				'id'      => $coin_id,
				'label'   => Paygate::coinLabel($coin),
				'logo'    => $coin['logo'],
				'enabled' => !empty($saved[$coin_id]['enabled']),
				'wallet'  => (string)($saved[$coin_id]['wallet'] ?? '')
			];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/paygatedottocrypto/payment/paygatedottocrypto', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/paygatedottocrypto/payment/paygatedottocrypto')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		$api_domain = trim((string)($post['payment_paygatedottocrypto_api_domain'] ?? ''));

		if ($api_domain !== '' && Paygate::normaliseHost($api_domain, '') === '') {
			$json['error']['api_domain'] = $this->language->get('error_api_domain');
		}

		$tolerance = (string)($post['payment_paygatedottocrypto_tolerance'] ?? '0.99');

		if (!isset($this->tolerances()[$tolerance])) {
			$tolerance = '0.99';
		}

		// Coins: only ids from the live list (or already saved) are kept, with a snapshot of label/logo.
		$saved = $this->config->get('payment_paygatedottocrypto_coins');
		$saved = is_array($saved) ? $saved : [];
		$map = $this->coinMap($api_domain, true) + $saved;

		// No coin table in the form (coin list unavailable): the saved coins are kept as they are.
		if (isset($post['payment_paygatedottocrypto_coins']) && is_array($post['payment_paygatedottocrypto_coins'])) {
			$posted = $post['payment_paygatedottocrypto_coins'];
		} else {
			$posted = $saved;
		}

		$coins = [];
		$usable = false;

		foreach ($posted as $coin_id => $row) {
			$coin_id = (string)$coin_id;

			if (!is_array($row) || !isset($map[$coin_id]) || !Paygate::isCoinId($coin_id)) {
				continue;
			}

			$wallet = trim(html_entity_decode((string)($row['wallet'] ?? ''), ENT_QUOTES, 'UTF-8'));
			$enabled = !empty($row['enabled']);

			if ($wallet === '' && !$enabled) {
				continue;
			}

			if ($wallet !== '' && !Paygate::isAddress($wallet)) {
				$json['error']['coin_' . preg_replace('/[^a-z0-9]+/', '-', $coin_id)] = $this->language->get('error_wallet_format');
			}

			$coins[$coin_id] = [
				'enabled' => $enabled ? 1 : 0,
				'wallet'  => $wallet,
				'label'   => (string)($map[$coin_id]['label'] ?? strtoupper($coin_id)),
				'chain'   => (string)($map[$coin_id]['chain'] ?? ''),
				'logo'    => (string)($map[$coin_id]['logo'] ?? '')
			];

			if ($enabled && $wallet !== '') {
				$usable = true;
			}
		}

		if (!empty($post['payment_paygatedottocrypto_status']) && !$usable) {
			$json['error']['warning'] = $this->language->get('error_no_coin');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$settings = [
				'payment_paygatedottocrypto_title'            => (string)($post['payment_paygatedottocrypto_title'] ?? ''),
				'payment_paygatedottocrypto_description'      => (string)($post['payment_paygatedottocrypto_description'] ?? ''),
				'payment_paygatedottocrypto_api_domain'       => $api_domain === '' ? '' : Paygate::normaliseHost($api_domain, ''),
				'payment_paygatedottocrypto_tolerance'        => $tolerance,
				'payment_paygatedottocrypto_fees'             => !empty($post['payment_paygatedottocrypto_fees']) ? 1 : 0,
				'payment_paygatedottocrypto_coins'            => $coins,
				'payment_paygatedottocrypto_order_status_id'  => (int)($post['payment_paygatedottocrypto_order_status_id'] ?? 1),
				'payment_paygatedottocrypto_paid_status_id'   => (int)($post['payment_paygatedottocrypto_paid_status_id'] ?? 2),
				'payment_paygatedottocrypto_failed_status_id' => (int)($post['payment_paygatedottocrypto_failed_status_id'] ?? 10),
				'payment_paygatedottocrypto_geo_zone_id'      => (int)($post['payment_paygatedottocrypto_geo_zone_id'] ?? 0),
				'payment_paygatedottocrypto_status'           => !empty($post['payment_paygatedottocrypto_status']) ? 1 : 0,
				'payment_paygatedottocrypto_sort_order'       => (int)($post['payment_paygatedottocrypto_sort_order'] ?? 0)
			];

			$this->model_setting_setting->editSetting('payment_paygatedottocrypto', $settings);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Called by OpenCart when the payment extension is installed.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->install();

		// Lets PayGate's callback through while the store is in maintenance mode.
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->eventCode());

		$this->model_setting_event->addEvent([
			'code'        => $this->eventCode(),
			'description' => 'PayGate.to crypto: payment confirmations while the store is in maintenance mode',
			'trigger'     => 'catalog/controller/common/maintenance/after',
			'action'      => 'extension/paygatedottocrypto/payment/paygatedottocrypto.maintenance',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	/**
	 * Called by OpenCart when the payment extension is uninstalled. Payment records are kept.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->eventCode());
	}

	/**
	 * Event code of this payment method (each method has its own, so either can be uninstalled alone).
	 *
	 * @return string
	 */
	protected function eventCode(): string {
		return 'paygatedottocrypto';
	}

	/**
	 * Crypto payment details tab on the admin order page.
	 *
	 * @return string
	 */
	public function order(): string {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;

		$this->load->model('extension/paygatedottocrypto/payment/paygatedottocrypto');

		$payment = $order_id ? $this->model_extension_paygatedottocrypto_payment_paygatedottocrypto->getPayment($order_id) : [];

		if (!$payment) {
			return '';
		}

		$data['payment'] = $payment;
		$data['mode'] = $payment['mode'] == 'multi' ? $this->language->get('text_mode_multi') : $this->language->get('text_mode_coin');
		$data['status_text'] = $this->language->get('text_status_' . (in_array($payment['status'], ['pending', 'paid', 'failed']) ? $payment['status'] : 'pending'));

		return $this->load->view('extension/paygatedottocrypto/payment/paygatedottocrypto_order', $data);
	}

	/**
	 * Underpaid tolerance options (multiplier => label).
	 *
	 * @return array<string, string>
	 */
	protected function tolerances(): array {
		return ['1' => '0%', '0.99' => '1%', '0.98' => '2%', '0.97' => '3%', '0.96' => '4%', '0.95' => '5%', '0.94' => '6%', '0.93' => '7%', '0.92' => '8%', '0.91' => '9%', '0.90' => '10%'];
	}

	/**
	 * Live coin list, cached for an hour.
	 *
	 * @param string $api_domain
	 * @param bool   $force
	 *
	 * @return array<string, array<string, string>>
	 */
	private function coinMap(string $api_domain, bool $force): array {
		$api = new Paygate($api_domain);
		$cache_key = 'paygatedottocrypto.coins.' . md5($api->getHost());

		if (!$force) {
			$cached = $this->cache->get($cache_key);

			if (is_array($cached) && $cached) {
				return $cached;
			}
		}

		$data = $api->get('info.php');
		$map = is_array($data) ? Paygate::flattenCoins($data) : [];

		if ($map) {
			$this->cache->set($cache_key, $map, 3600);
		} elseif ($force) {
			$cached = $this->cache->get($cache_key);
			$map = is_array($cached) ? $cached : [];
		}

		return $map;
	}
}
