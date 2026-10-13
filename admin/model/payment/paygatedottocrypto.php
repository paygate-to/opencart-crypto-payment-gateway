<?php
namespace Opencart\Admin\Model\Extension\Paygatedottocrypto\Payment;
/**
 * Class Paygatedottocrypto
 *
 * @package Opencart\Admin\Model\Extension\Paygatedottocrypto\Payment
 */
class Paygatedottocrypto extends \Opencart\System\Engine\Model {
	/**
	 * Payment records table, shared by the individual-coin and Multicoin methods.
	 * It is kept on uninstall so order payment details are never lost.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paygatedottocrypto` (
			`order_id` INT(11) NOT NULL,
			`mode` VARCHAR(8) NOT NULL,
			`coin` VARCHAR(80) NOT NULL DEFAULT '',
			`coin_label` VARCHAR(128) NOT NULL DEFAULT '',
			`address_in` VARCHAR(255) NOT NULL DEFAULT '',
			`payment_token` TEXT NOT NULL,
			`ipn_token` TEXT NOT NULL,
			`display_total` VARCHAR(40) NOT NULL DEFAULT '',
			`qr_code` MEDIUMTEXT NOT NULL,
			`amount` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`currency_code` VARCHAR(3) NOT NULL DEFAULT '',
			`tolerance` DECIMAL(4,2) NOT NULL DEFAULT '0.99',
			`fees` TINYINT(1) NOT NULL DEFAULT '0',
			`api_host` VARCHAR(253) NOT NULL DEFAULT '',
			`nonce` VARCHAR(64) NOT NULL,
			`status_key` VARCHAR(64) NOT NULL,
			`callback_url` TEXT NOT NULL,
			`quoted_at` DATETIME NOT NULL,
			`status` VARCHAR(16) NOT NULL DEFAULT 'pending',
			`received_total` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`received_coin` DECIMAL(30,10) NOT NULL DEFAULT '0.0000000000',
			`txids` TEXT NOT NULL,
			`paid_coin` VARCHAR(80) NOT NULL DEFAULT '',
			`value_coin` VARCHAR(40) NOT NULL DEFAULT '',
			`txid_in` VARCHAR(255) NOT NULL DEFAULT '',
			`txid_out` VARCHAR(255) NOT NULL DEFAULT '',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	/**
	 * @param int $order_id
	 *
	 * @return array<string, mixed>
	 */
	public function getPayment(int $order_id): array {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "paygatedottocrypto") . "'");

		if (!$query->num_rows) {
			return [];
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "paygatedottocrypto` WHERE `order_id` = '" . (int)$order_id . "'");

		return $query->row;
	}
}
