<?php
namespace Opencart\Catalog\Model\Extension\Paygatedottocrypto\Payment;
/**
 * Class PaygatedottocryptoMulti
 *
 * Crypto payments, Multicoin: the customer picks any enabled coin on the PayGate hosted page.
 *
 * @package Opencart\Catalog\Model\Extension\Paygatedottocrypto\Payment
 */
class PaygatedottocryptoMulti extends Paygatedottocrypto {
	/**
	 * Payout wallet setting per network family accepted by crypto/multi-hosted-wallet.php.
	 */
	public const WALLETS = ['evm', 'btc', 'bitcoincash', 'ltc', 'doge', 'solana', 'trc20', 'xmr', 'zec', 'ton'];

	/**
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/paygatedottocrypto/payment/paygatedottocrypto_multi');

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->getWallets()) {
			$status = false;
		} else {
			$status = $this->inGeoZone((int)$this->config->get('payment_paygatedottocrypto_multi_geo_zone_id'), $address);
		}

		$method_data = [];

		if ($status) {
			$title = (string)$this->config->get('payment_paygatedottocrypto_multi_title');

			if ($title === '') {
				$title = $this->language->get('heading_title');
			}

			$option_data['paygatedottocrypto_multi'] = [
				'code' => 'paygatedottocrypto_multi.paygatedottocrypto_multi',
				'name' => $title
			];

			$method_data = [
				'code'       => 'paygatedottocrypto_multi',
				'name'       => $title,
				'option'     => $option_data,
				'sort_order' => (int)$this->config->get('payment_paygatedottocrypto_multi_sort_order')
			];
		}

		return $method_data;
	}

	/**
	 * Configured payout wallets, keyed by network family.
	 *
	 * @return array<string, string>
	 */
	public function getWallets(): array {
		$wallets = [];

		foreach (self::WALLETS as $family) {
			$wallet = trim((string)$this->config->get('payment_paygatedottocrypto_multi_wallet_' . $family));

			if ($wallet !== '') {
				$wallets[$family] = $wallet;
			}
		}

		return $wallets;
	}
}
