<?php
namespace Opencart\System\Library\Extension\Paygatedottocrypto;
/**
 * PayGate.to crypto API client, shared by the admin and catalog sides of the extension.
 *
 * - API host: the merchant's dedicated API endpoint (e.g. api-yourshop-1a2b….paygate.to) from the
 *   "API Domain" setting, or the shared api.paygate.to when that setting is empty or invalid.
 * - Coin list: the live list from crypto/info.php, flattened into one map keyed by coin id
 *   ("btc", "erc20_usdc", "avax-c_usdc.e"); the API path of a coin is its id with "_" replaced by "/".
 * - Callback signature: every PayGate callback is signed with RSA-2048 / SHA-256 (PKCS#1 v1.5) over
 *   the exact URL PayGate requested. See https://paygate.to/docs/crypto-payment-api/#callback-event
 *
 * @package Opencart\System\Library\Extension\Paygatedottocrypto
 */
class Paygate {
	public const DEFAULT_API_HOST = 'api.paygate.to';
	public const DEFAULT_CHECKOUT_HOST = 'checkout.paygate.to';

	private string $host;

	/**
	 * @param string $api_domain "API Domain" setting value (may be empty)
	 */
	public function __construct(string $api_domain = '') {
		$this->host = self::normaliseHost($api_domain, self::DEFAULT_API_HOST);
	}

	/**
	 * Host actually used for API calls.
	 *
	 * @return string
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * Normalise a domain setting: strips a scheme, path, port-less trailing dot and case.
	 * Anything that is not a plain host name falls back to $default.
	 *
	 * @param string $value
	 * @param string $default
	 *
	 * @return string
	 */
	public static function normaliseHost(string $value, string $default): string {
		$host = strtolower(trim($value));
		$host = (string)preg_replace('#^https?://#', '', $host);
		$host = rtrim(explode('/', $host, 2)[0], '.');

		if ($host === '' || !preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
			return $default;
		}

		return $host;
	}

	/**
	 * GET an API path (relative to https://{host}/crypto/) and decode the JSON answer.
	 *
	 * @param string               $path  e.g. "info.php" or "erc20/usdc/convert.php"
	 * @param array<string, mixed> $query
	 *
	 * @return array<mixed>|null
	 */
	public function get(string $path, array $query = []): ?array {
		$url = 'https://' . $this->host . '/crypto/' . $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');

		return $this->request($url);
	}

	/**
	 * POST a JSON body to an API path and decode the JSON answer.
	 *
	 * @param string               $path
	 * @param array<string, mixed> $payload
	 *
	 * @return array<mixed>|null
	 */
	public function postJson(string $path, array $payload): ?array {
		return $this->request('https://' . $this->host . '/crypto/' . $path, (string)json_encode($payload));
	}

	/**
	 * @param string      $url
	 * @param string|null $json_body POST body, or null for GET
	 *
	 * @return array<mixed>|null
	 */
	private function request(string $url, ?string $json_body = null): ?array {
		$curl = curl_init($url);

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_USERAGENT      => 'OpenCart-PayGate-Crypto/1.0'
		];

		if ($json_body !== null) {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $json_body;
			$options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
		}

		curl_setopt_array($curl, $options);

		$body = curl_exec($curl);
		$code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		if (!is_string($body) || $code < 200 || $code >= 300) {
			return null;
		}

		$data = json_decode($body, true);

		return is_array($data) ? $data : null;
	}

	/**
	 * Flatten the crypto/info.php answer into a coin map keyed by coin id.
	 *
	 * Standalone coins (btc, ltc, eth, …) use their ticker as id; chain tokens use "{chain}_{ticker}".
	 * Each entry: id, ticker, chain, label, logo, path.
	 *
	 * @param array<mixed> $data
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function flattenCoins(array $data): array {
		$map = [];

		foreach ($data as $key => $entry) {
			if (!is_array($entry)) {
				continue;
			}

			if (isset($entry['ticker'])) {
				$coin = self::coinEntry('', $entry);

				if ($coin) {
					$map[$coin['id']] = $coin;
				}

				continue;
			}

			foreach ($entry as $child) {
				if (is_array($child) && isset($child['ticker'])) {
					$coin = self::coinEntry((string)$key, $child);

					if ($coin) {
						$map[$coin['id']] = $coin;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * @param string       $chain
	 * @param array<mixed> $entry
	 *
	 * @return array<string, string>|null
	 */
	private static function coinEntry(string $chain, array $entry): ?array {
		$ticker = strtolower((string)$entry['ticker']);
		$chain = strtolower($chain);
		$id = $chain !== '' ? $chain . '_' . $ticker : $ticker;

		if (!self::isCoinId($id)) {
			return null;
		}

		$logo = isset($entry['logo']) ? (string)$entry['logo'] : '';

		if (!preg_match('#^https://#i', $logo)) {
			$logo = '';
		}

		return [
			'id'     => $id,
			'ticker' => $ticker,
			'chain'  => $chain,
			'label'  => isset($entry['coin']) ? (string)$entry['coin'] : strtoupper($ticker),
			'logo'   => $logo,
			'path'   => self::coinPath($id)
		];
	}

	/**
	 * A coin id as used by PayGate: lowercase letters, digits, ".", "-" and one optional "_" separator.
	 *
	 * @param string $id
	 *
	 * @return bool
	 */
	public static function isCoinId(string $id): bool {
		return (bool)preg_match('/^[a-z0-9.-]{1,32}(_[a-z0-9.-]{1,32})?$/', $id) && strpos($id, '..') === false;
	}

	/**
	 * API path for a coin id ("erc20_usdc" -> "erc20/usdc").
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public static function coinPath(string $id): string {
		return str_replace('_', '/', strtolower($id));
	}

	/**
	 * Display label, e.g. "USD Coin (ERC20)".
	 *
	 * @param array<string, string> $coin
	 *
	 * @return string
	 */
	public static function coinLabel(array $coin): string {
		$label = $coin['label'] ?? '';

		if (!empty($coin['chain'])) {
			$label .= ' (' . strtoupper($coin['chain']) . ')';
		}

		return $label;
	}

	/**
	 * Random hex token (callback nonce, status page key).
	 *
	 * @return string
	 */
	public static function token(): string {
		return bin2hex(random_bytes(16));
	}

	/**
	 * A pay-in address, as returned by PayGate, that is safe to store and display.
	 *
	 * @param string $address
	 *
	 * @return bool
	 */
	public static function isAddress(string $address): bool {
		return (bool)preg_match('#^[A-Za-z0-9:._+/=-]{8,200}$#', $address);
	}

	/**
	 * A blockchain transaction id as sent in a callback (hex, base58 or base64 depending on the chain).
	 *
	 * @param string $txid
	 *
	 * @return bool
	 */
	public static function isTxid(string $txid): bool {
		return (bool)preg_match('#^[A-Za-z0-9:._+/=-]{1,255}$#', $txid);
	}

	/**
	 * Callback URL registered with PayGate for an order.
	 *
	 * Built on the plain index.php?route= form of the store URL, so SEO URL rewriting can never
	 * change the address PayGate calls (and signs).
	 *
	 * @param string $store_url store base URL ending in "/"
	 * @param int    $order_id
	 * @param string $nonce
	 *
	 * @return string
	 */
	public static function callbackUrl(string $store_url, int $order_id, string $nonce): string {
		return rtrim($store_url, '/') . '/index.php?route=extension/paygatedottocrypto/payment/paygatedottocrypto.callback&order_id=' . $order_id . '&nonce=' . rawurlencode($nonce);
	}

	/**
	 * Parameters of the current callback, parsed from the same raw query string the signature
	 * covers. POST data and anything OpenCart may have changed in $_GET are never used.
	 *
	 * @return array<string, mixed>
	 */
	public static function callbackParams(): array {
		$params = [];

		parse_str(self::rawQueryString(), $params);

		return $params;
	}

	/**
	 * Verify the PayGate signature of the current callback request.
	 *
	 * The signed string is the callback URL this extension registered, up to its "?", plus the raw
	 * query string exactly as it arrived. Nothing is rebuilt from parsed parameters and the Host
	 * header is never trusted. The raw query comes from REQUEST_URI (the URL as requested), so a
	 * web-server rewrite that changes QUERY_STRING cannot break verification.
	 *
	 * @param string $registered_callback_url callback URL given to PayGate for this order
	 * @param string $key_dir                 directory holding paygate_callback_public_{key id}.pem
	 *
	 * @return array{verified: bool, reason: string}
	 */
	public static function verifyCallback(string $registered_callback_url, string $key_dir): array {
		$signature = isset($_SERVER['HTTP_X_PAYGATE_SIGNATURE']) ? trim((string)$_SERVER['HTTP_X_PAYGATE_SIGNATURE']) : '';

		if ($signature === '') {
			return ['verified' => false, 'reason' => 'signature_absent'];
		}

		if (!function_exists('openssl_verify')) {
			return ['verified' => false, 'reason' => 'openssl_unavailable'];
		}

		$key_id = isset($_SERVER['HTTP_X_PAYGATE_KEY_ID']) ? trim((string)$_SERVER['HTTP_X_PAYGATE_KEY_ID']) : '';

		if ($key_id === '') {
			$key_id = 'v1';
		}

		// Never let a header reach the filesystem unchecked.
		if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $key_id)) {
			return ['verified' => false, 'reason' => 'unknown_key_id'];
		}

		$pem_file = rtrim($key_dir, '/') . '/paygate_callback_public_' . $key_id . '.pem';

		if (!is_file($pem_file) || !is_readable($pem_file)) {
			return ['verified' => false, 'reason' => 'unknown_key_id'];
		}

		$public_key = openssl_pkey_get_public((string)file_get_contents($pem_file));

		if ($public_key === false) {
			return ['verified' => false, 'reason' => 'bad_public_key'];
		}

		$raw_signature = base64_decode($signature, true);

		if ($raw_signature === false || $raw_signature === '') {
			return ['verified' => false, 'reason' => 'signature_not_base64'];
		}

		$pos = strpos($registered_callback_url, '?');
		$base = $pos === false ? $registered_callback_url : substr($registered_callback_url, 0, $pos);

		if (!preg_match('#^https?://[^/?\#\s]+#i', $base)) {
			return ['verified' => false, 'reason' => 'no_registered_callback'];
		}

		$signed = $base;
		$query = self::rawQueryString();

		if ($query !== '') {
			$signed .= '?' . $query;
		}

		$result = openssl_verify($signed, $raw_signature, $public_key, OPENSSL_ALGO_SHA256);

		if ($result === 1) {
			return ['verified' => true, 'reason' => ''];
		}

		return ['verified' => false, 'reason' => $result === -1 ? 'openssl_error' : 'signature_mismatch'];
	}

	/**
	 * The raw query string of the current request, byte for byte.
	 *
	 * @return string
	 */
	public static function rawQueryString(): string {
		$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
		$pos = strpos($uri, '?');

		if ($pos !== false) {
			$query = substr($uri, $pos + 1);
			$hash = strpos($query, '#');

			return $hash === false ? $query : substr($query, 0, $hash);
		}

		return isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
	}
}
