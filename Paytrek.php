<?php
/**
 * Paytrek Payment System PHP Client
 */
class Paytrek
{

  function __construct() {
    $this->api_key = null;
    $this->api_secret = null;
    $this->version = null;
    $this->sandbox = false;
    $this->endpoint = null;
    $this->turkey = false;
    $this->headers = array(
      'Content-Type: application/json'
    );
    $this->response = null;
    $this->secure_3D = false;
    $this->errors = array();
    $this->forward_url = null;
    $this->fraud_check_comment = 'I accept the result of the fraud check.';
  }

  function configure($api_key, $api_secret, $version = 'v2') {
    $this->api_key = $api_key;
    $this->api_secret = $api_secret;
    $this->version = $version;

    $credentials = base64_encode($this->api_key . ':' . $this->api_secret);

    array_push($this->headers, 'Authorization: Basic ' . $credentials);

    return true;
  }

  function enter_sandbox() {
    $this->sandbox = true;
  }

  function enter_turkey() {
    $this->turkey = true;
  }

  function enable_3D_auth() {
    $this->secure_3D = true;
  }

  private function build_endpoint($path = '/') {
    $base = 'https://' .
      ( $this->sandbox === true ? 'sandbox.' : 'secure.' ) .
      'paytrek.com' .
      ( $this->turkey === true ? '.tr' : '' ) .
      '/api';

    $version = strlen($this->version) > 0 ? '/' . $this->version : '';

    $this->endpoint = $base . $version . $path;

    return $this->endpoint;
  }

  function direct_charge($payload) {
    $this->build_endpoint('/direct_charge/');

    if (!$payload['customer_ip_address']) {
      $payload['customer_ip_address'] = $this->get_ip();
    }

    $payload['secure_option'] = $this->secure_3D;
    if (!isset($payload['half_secure'])) {
      $payload['half_secure'] = $this->secure_3D;
    }

    $this->response = $this->send($payload);

    if (isset($this->response['sale_token']) and !empty($this->response['sale_token'])) {
      if ($this->response['secure_option'] == 1) {
        $this->forward_url = $this->response['forward_url'];
      }
    }
    else {
      $this->errors = $this->response;
    }

    return $this;
  }

  function get_sale($token) {
    $this->build_endpoint('/sale/' . $token . '/');

    return $this->send(null);
  }

  function is_sale_successful($token = '', $sale = null) {
    $obj = empty($sale) ? $this->get_sale($token) : $sale;

    if (!empty($obj['transactions']) and sizeof($obj['transactions']) > 0) {
      $success = true;
      foreach ($obj['transactions'] as $key => $tobj) {
        if ($tobj['succeeded'] == 0 or $tobj['succeeded'] === false) {
          $success = false;
          if (!empty($tobj['paytrek_error'])) {
            array_push($this->errors, $tobj['paytrek_error']['message'] . '. ' . $tobj['paytrek_error']['customer_message']);
          }
        }
      }

      return $success;
    }

    return false;
  }

  function cancel($token, $comment = '') {
    $this->build_endpoint('/cancel/');

    if ($comment == '') {
      $comment = $this->fraud_check_comment;
    }

    $payload = [
      'sale_token' => $token,
      'comments' => $comment
    ];

    return $this->send($payload);
  }

  function refund($token, $amount, $comment = '') {
    $this->build_endpoint('/refund/');

    if ($comment == '') {
      $comment = $this->fraud_check_comment;
    }

    $payload = [
      'sale_token' => $token,
      'amount' => $amount,
      'comments' => $comment
    ];

    return $this->send($payload);
  }

  function installments($amount, $bin = null) {
    $amount_query = '?amount=' . $amount;
    $bin_query = is_null($bin) ? '' : '&bin_number=' . $bin;
    $this->build_endpoint('/installments/' . $amount_query . $bin_query);

    return $this->send(null);
  }

  function auth() {
    if (empty($this->errors) and !empty($this->forward_url)) {
      header('Location: ' . $this->forward_url);
      header('Connection: close');
      return;
    }

    return false;
  }

  function get_response() {
    return $this->response;
  }

  private function send($payload = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    if (!is_null($payload)) {
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

    $response = curl_exec($ch);
    curl_close($ch);

    $obj = json_decode($response, true);
    if (json_last_error() != JSON_ERROR_NONE) {
      throw new Exception($response);
    }

    return $obj;
  }

  function get_ip() {
    if (isset($_SERVER)) {
  		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) return $_SERVER["HTTP_X_FORWARDED_FOR"];
  		if (isset($_SERVER["HTTP_CLIENT_IP"])) return $_SERVER["HTTP_CLIENT_IP"];

  		return $_SERVER["REMOTE_ADDR"];
  	}
  	if (getenv('HTTP_X_FORWARDED_FOR')) return getenv('HTTP_X_FORWARDED_FOR');
  	if (getenv('HTTP_CLIENT_IP')) return getenv('HTTP_CLIENT_IP');

  	return getenv('REMOTE_ADDR');
  }
}

?>
