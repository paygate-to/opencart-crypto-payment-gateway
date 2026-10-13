<?php
namespace Opencart\Catalog\Model\Extension\Paygatedottocrypto\Payment;
/**
 * Class Paygatedottocrypto
 *
 * Crypto payments, individual coins (QR code and address shown on your own store).
 * Also holds the per-order payment record shared with the Multicoin method.
 *
 * @package Opencart\Catalog\Model\Extension\Paygatedottocrypto\Payment
 */
class Paygatedottocrypto extends \Opencart\System\Engine\Model {
	/**
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto');

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->getEnabledCoins()) {
			$status = false;
		} else {
			$status = $this->inGeoZone((int)$this->config->get('payment_paygatedottocrypto_geo_zone_id'), $address);
		}

		$method_data = [];

		if ($status) {
			$title = (string)$this->config->get('payment_paygatedottocrypto_title');

			if ($title === '') {
				$title = $this->language->get('heading_title');
			}

			$option_data['paygatedottocrypto'] = [
				'code' => 'paygatedottocrypto.paygatedottocrypto',
				'name' => $title
			];

			$method_data = [
				'code'       => 'paygatedottocrypto',
				'name'       => $title,
				'option'     => $option_data,
				'sort_order' => (int)$this->config->get('payment_paygatedottocrypto_sort_order')
			];
		}

		return $method_data;
	}

	/**
	 * Coins that are ticked AND have a payout wallet, keyed by coin id.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getEnabledCoins(): array {
		$config = $this->config->get('payment_paygatedottocrypto_coins');
		$coins = [];

		if (is_array($config)) {
			foreach ($config as $coin_id => $coin) {
				if (!is_array($coin) || !\Opencart\System\Library\Extension\Paygatedottocrypto\Paygate::isCoinId((string)$coin_id)) {
					continue;
				}

				if (!empty($coin['enabled']) && isset($coin['wallet']) && trim((string)$coin['wallet']) !== '') {
					$coins[(string)$coin_id] = [
						'id'     => (string)$coin_id,
						'wallet' => trim((string)$coin['wallet']),
						'label'  => (string)($coin['label'] ?? strtoupper((string)$coin_id)),
						'chain'  => (string)($coin['chain'] ?? ''),
						'logo'   => (string)($coin['logo'] ?? ''),
						'path'   => \Opencart\System\Library\Extension\Paygatedottocrypto\Paygate::coinPath((string)$coin_id)
					];
				}
			}
		}

		return $coins;
	}

	/**
	 * @param int                  $geo_zone_id
	 * @param array<string, mixed> $address
	 *
	 * @return bool
	 */
	public function inGeoZone(int $geo_zone_id, array $address): bool {
		if (!$this->config->get('config_checkout_payment_address') || !$geo_zone_id) {
			return true;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "' AND `country_id` = '" . (int)($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0')");

		return (bool)$query->num_rows;
	}

	/**
	 * Payment record of an order, or an empty array. quote_age is the age of the quote in seconds.
	 *
	 * @param int $order_id
	 *
	 * @return array<string, mixed>
	 */
	public function getPayment(int $order_id): array {
		$query = $this->db->query("SELECT *, TIMESTAMPDIFF(SECOND, `quoted_at`, NOW()) AS `quote_age` FROM `" . DB_PREFIX . "paygatedottocrypto` WHERE `order_id` = '" . (int)$order_id . "'");

		return $query->row;
	}

	/**
	 * Create the payment record of an order, or replace its quote.
	 *
	 * An existing record is only replaced while nothing has been received for it, so a paid or
	 * part-paid order can never be reset to a new address or amount.
	 *
	 * @param int                  $order_id
	 * @param array<string, mixed> $data
	 *
	 * @return bool false when an existing record could not be replaced
	 */
	public function savePayment(int $order_id, array $data): bool {
		$fields = "`mode` = '" . $this->db->escape((string)$data['mode']) . "', `coin` = '" . $this->db->escape((string)($data['coin'] ?? '')) . "', `coin_label` = '" . $this->db->escape((string)($data['coin_label'] ?? '')) . "', `address_in` = '" . $this->db->escape((string)($data['address_in'] ?? '')) . "', `payment_token` = '" . $this->db->escape((string)($data['payment_token'] ?? '')) . "', `ipn_token` = '" . $this->db->escape((string)($data['ipn_token'] ?? '')) . "', `display_total` = '" . $this->db->escape((string)($data['display_total'] ?? '')) . "', `qr_code` = '" . $this->db->escape((string)($data['qr_code'] ?? '')) . "', `amount` = '" . (float)$data['amount'] . "', `currency_code` = '" . $this->db->escape((string)$data['currency_code']) . "', `tolerance` = '" . (float)$data['tolerance'] . "', `fees` = '" . (int)$data['fees'] . "', `api_host` = '" . $this->db->escape((string)$data['api_host']) . "', `nonce` = '" . $this->db->escape((string)$data['nonce']) . "', `status_key` = '" . $this->db->escape((string)$data['status_key']) . "', `callback_url` = '" . $this->db->escape((string)$data['callback_url']) . "', `quoted_at` = NOW(), `date_modified` = NOW()";

		$this->db->query("INSERT INTO `" . DB_PREFIX . "paygatedottocrypto` SET `order_id` = '" . (int)$order_id . "', " . $fields . ", `status` = 'pending', `txids` = '', `date_added` = NOW() ON DUPLICATE KEY UPDATE `order_id` = `order_id`");

		if ($this->db->countAffected() > 0) {
			return true;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "paygatedottocrypto` SET " . $fields . " WHERE `order_id` = '" . (int)$order_id . "' AND `status` = 'pending' AND `txids` = ''");

		return $this->db->countAffected() > 0;
	}

	/**
	 * Add a confirmed blockchain transaction to the payment record.
	 *
	 * Atomic: a transaction id is counted once, and nothing is added to a paid record, so a
	 * repeated or concurrent callback can never credit an order twice. Payments add up, so a
	 * customer who sent too little can send the rest to the same address.
	 *
	 * @param int                  $order_id
	 * @param array<string, mixed> $data paid_coin, value_coin, value_fiat, same_coin, txid_in, txid_out
	 *
	 * @return bool true when this call added the transaction
	 */
	public function addTransaction(int $order_id, array $data): bool {
		$txid = $this->db->escape((string)$data['txid_in']);

		$this->db->query("UPDATE `" . DB_PREFIX . "paygatedottocrypto` SET `received_total` = `received_total` + '" . (float)$data['value_fiat'] . "', `received_coin` = `received_coin` + '" . ($data['same_coin'] ? $this->db->escape(number_format((float)$data['value_coin'], 10, '.', '')) : '0') . "', `txids` = IF(`txids` = '', '" . $txid . "', CONCAT(`txids`, ',', '" . $txid . "')), `paid_coin` = '" . $this->db->escape((string)$data['paid_coin']) . "', `value_coin` = '" . $this->db->escape((string)$data['value_coin']) . "', `txid_in` = '" . $txid . "', `txid_out` = '" . $this->db->escape((string)$data['txid_out']) . "', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "' AND `status` <> 'paid' AND FIND_IN_SET('" . $txid . "', `txids`) = 0");

		return $this->db->countAffected() > 0;
	}

	/**
	 * Mark the record paid. Atomic: true only for the call that made the change.
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function setPaid(int $order_id): bool {
		$this->db->query("UPDATE `" . DB_PREFIX . "paygatedottocrypto` SET `status` = 'paid', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "' AND `status` <> 'paid'");

		return $this->db->countAffected() > 0;
	}

	/**
	 * Mark the record underpaid (more can still be sent to the same address).
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function setUnderpaid(int $order_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "paygatedottocrypto` SET `status` = 'failed', `date_modified` = NOW() WHERE `order_id` = '" . (int)$order_id . "' AND `status` = 'pending'");
	}
}
