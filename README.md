# Paytrek Payment System API PHP Client

This is a PHP client to process and manage payments over [Paytrek][591c4f35]. This was a client work and it doesn't support all API endpoints Paytrek has. Please see the supported methods below.

  [591c4f35]: https://www.paytrek.com.tr "Paytrek"

You can find details about API endpoints at [The Paytrek official API Docs][c6a1f552].

  [c6a1f552]: https://paytrektr.docs.apiary.io "The Paytrek official API Docs"

## Install

```sh
# enter your project directory and fetch the code.
git clone git@github.com:muratgozel/paytrek-php-client.git
```

## Initiation

```php
require_once('paytrek-php-client/Paytrek.php');

$payment = new Paytrek();
```

## Authorization

```php
$payment->configure($paytrek_api_key, $paytrek_api_secret);

$payment->enter_turkey(); # if the company registered in turkey
```

## Direct Charge (3D Enabled)

Make a payment request and wait for the user to authorize the payment:

```php
# enable 3D auth
$payment->enable_3D_auth();

# create payload
$payload = array(
  'currency' => 'TRY',
  'order_id' => $order_id,
  'amount' => $amount,
  'number' => $card_num,
  'expiration' => $card_expire_month . '/' . $card_expire_year,
  'cvc' => $cvc,
  'card_holder_name' => $card_holder,
  'installment' => $installment,
  'return_url' => $return_url,
  'items' => array(),
  'customer_ip_address' => $customer_ip,
  'customer_first_name' => $firstname,
  'customer_last_name' => $lastname,
  'customer_email' => $customer_email,
  'billing_address' => $address,
  'billing_city' => $city,
  'billing_country' => 'TR',
  'sale_data' => []
);

# direct charge request
try {
  $payment->direct_charge($payload);
} catch (\Exception $e) {
  # bad response from server
  # handle error $e
  # exit
}

$response = $payment->get_response();

# check payment errors
if (sizeof($payment->errors) > 0) {
  # errors is available at $payment->errors
  # exit
}

# payment auth successful
# you may probably want to save the $response to some database

# redirect user to the 3D auth page of the bank
$payment->auth();
```

In return url, verify the payment with the token Paytrek attach to the url:

```php
# get sale token in the url
$callbackurl = $_SERVER["REQUEST_URI"];
$parsed = parse_url($callbackurl);
parse_str($parsed['query'], $token);

# get sale info from paytrek
$sale = $payment->get_sale($token['token']);
$success = $payment->is_sale_successful($token['token'], $sale);

if ($success === true) {
  # sale successful
}

# or show the error to the user with printing $payment->errors[0]
```

## Installments

Fetches the list of installments available to the shopper.

```php
# $bin is optional and it is the first 6 digit of the card
$response = $payment->installments($amount, $bin = null);
```

## Cancel Sale

```php
$response = $payment->cancel($token, $comment = '');
```

## Refund

```php
$response = $payment->refund($token, $amount, $comment = '');
```

### Warning
This is not an official client. It doesn't support all API endpoints provided by Paytrek. However, you can make pull request and contribute to this client to make it more complete.
