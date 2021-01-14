<?php
// A thin wrapper for DHL eCommerce API

namespace apih\DHLeCommerce;

class Client
{
	const SANDBOX_URL = 'https://sandbox.dhlecommerce.asia/';
	const LIVE_URL = 'https://api.dhlecommerce.dhl.com/';

	protected $client_id;
	protected $password;
	protected $access_token;
	protected $url;
	protected $use_ssl = true;
	protected $last_error;

	public function __construct($client_id, $password)
	{
		$this->client_id = $client_id;
		$this->password = $password;
		$this->url = self::LIVE_URL;
	}

	public function useSandbox($flag = true)
	{
		$this->url = $flag ? self::SANDBOX_URL : self::LIVE_URL;
	}

	public function useSsl($flag = true)
	{
		$this->use_ssl = $flag;
	}

	public function getUrl()
	{
		return $this->url;
	}

	public function getLastError()
	{
		return $this->last_error;
	}

	protected function logError($function, $request, $response)
	{
		$this->last_error = [
			'function' => $function,
			'request' => $request,
			'response' => $response
		];

		$error_message = 'DHL eCommerce Error:' . PHP_EOL;
		$error_message .= 'function: ' . $function . PHP_EOL;
		$error_message .= 'request: ' . PHP_EOL;
		$error_message .= '-> url: ' . $request['url'] . PHP_EOL;
		$error_message .= '-> data: ' . json_encode($request['data']) . PHP_EOL;
		$error_message .= 'response: ' . PHP_EOL;
		$error_message .= '-> http_code: ' . $response['http_code'] . PHP_EOL;
		$error_message .= '-> body: ' . $response['body'] . PHP_EOL;

		error_log($error_message);
	}

	protected function curlInit()
	{
		$this->last_error = null;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

		if ($this->use_ssl === false) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		}

		return $ch;
	}

	protected function curlExec($ch, $function, $url, $data = [])
	{
		$body = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$decoded_body = json_decode($body, true);

		if ($http_code !== 200 || json_last_error() !== JSON_ERROR_NONE) {
			$this->logError(
				$function,
				compact('url', 'data'),
				compact('http_code', 'body')
			);

			return null;
		}

		return $decoded_body;
	}

	protected function curlGetRequest($function, $action, $data = [])
	{
		$ch = $this->curlInit();

		curl_setopt($ch, CURLOPT_URL, $this->url . $action . (empty($data) ? '' : '?' . http_build_query($data)));

		return $this->curlExec($ch, $function, $this->url . $action);
	}

	protected function curlPostRequest($function, $action, $data = [])
	{
		$ch = $this->curlInit();

		curl_setopt($ch, CURLOPT_POST, true);

		if ($data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		}

		curl_setopt($ch, CURLOPT_URL, $this->url . $action);

		return $this->curlExec($ch, $function, $this->url . $action, $data);
	}

	public function getAccessToken()
	{
		$result = $this->curlGetRequest(__FUNCTION__, 'rest/v1/OAuth/AccessToken', [
			'clientId' => $this->client_id,
			'password' => $this->password
		]);

		$this->access_token = $result['accessTokenResponse']['token'];

		return $this->access_token;
	}

	public function setAccessToken($access_token)
	{
		$this->access_token = $access_token;
	}

	protected function generateHeader($message_type)
	{
		return [
			'messageType' => $message_type,
			'messageDateTime' => (new \DateTime())->format('c'),
			'messageVersion' => '1.4',
			'accessToken' => $this->access_token
		];
	}

	public function createLabel($body)
	{
		$label_request = [
			'hdr' => $this->generateHeader('LABEL'),
			'bd' => $body
		];

		$result = $this->curlPostRequest(__FUNCTION__, 'rest/v2/Label', ['labelRequest' => $label_request]);

		return $result;
	}

	public function reprintLabel($body)
	{
		$label_reprint_request = [
			'hdr' => $this->generateHeader('LABELREPRINT'),
			'bd' => $body
		];

		$result = $this->curlPostRequest(__FUNCTION__, 'rest/v2/Label/Reprint', ['labelReprintRequest' => $label_reprint_request]);

		return $result;
	}

	public function trackItem($body)
	{
		$track_item_request = [
			'hdr' => $this->generateHeader('TRACKITEM'),
			'bd' => $body
		];

		$result = $this->curlPostRequest(__FUNCTION__, 'rest/v3/Tracking', ['trackItemRequest' => $track_item_request]);

		return $result;
	}
}
?>