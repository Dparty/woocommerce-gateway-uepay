<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

function base64UrlEncode(string $data): string
{
  $base64Url = strtr(base64_encode($data), '+/', '-_');

  return rtrim($base64Url, '=');
}

function base64UrlDecode(string $base64Url): string
{
  return base64_decode(strtr($base64Url, '-_', '+/'));
}

function utf8($s)
{
  return mb_convert_encoding($s, 'UTF-8');
}

function planMap($payload)
{
  $stock = array();
  foreach ($payload as $key => $value) {
    $ukey = utf8($key);
    mb_convert_encoding(stringA($payload), "UTF-8");
    if (gettype($value) == "array") {
      $stock = array_merge($stock, planMap($value));
    } else {
      $uvalue = utf8($value);
      $stock[$ukey] = utf8($uvalue);
    }
  }
  return $stock;
}

function stringA($payload)
{
  $stringList = array();
  ksort($payload);
  foreach ($payload as $key => $value) {
    array_push($stringList, $key . "=" . $value);
  }
  return join("&", $stringList);
}

function sign($payload, $key)
{
  $plan = planMap($payload);
  return strtoupper(md5(stringA($plan) . "&key=" . $key));
}

function createPayment($merchantNo, $paykey, $orderNo, $amt, $merchantRedirectUrl, $notifyUrl, $body, $detail)
{
  $UEPAY_URL = 'https://openapi.uepay.mo/unionPayOnLinePay/unifiedorder';
  $payload = array(
    'merchantNo' => $merchantNo,
    'appSource' => '3',
    'appVersion' => '1.4',
    'requestType' => 'UNIONPAY',
    'tradeType' => 'UNIONPAY_ONLINE',
    'arguments.orderNo' => $orderNo,
    'arguments.body' => $body,
    'arguments.detail' => $detail,
    'arguments.amt' => $amt,
    'arguments.merchantRedirectUrl' => $merchantRedirectUrl,
    'arguments.notifyUrl' => $notifyUrl,
  );
  $json_pretty = json_encode($payload, JSON_PRETTY_PRINT);
  $appendVar = fopen('/var/www/php-log.txt','a');
  fwrite($appendVar, "$json_pretty\n");
  $signPayload = array(
    'appVersion' => '1.4',
    'body' => $body,
    'detail' => $detail,
    'merchantNo' => $merchantNo,
    'merchantRedirectUrl' => $merchantRedirectUrl,
    'notifyUrl' => $notifyUrl,
    'orderNo' => $orderNo,
    'requestType' => 'UNIONPAY',
    'tradeType' => 'UNIONPAY_ONLINE',
    'appSource' => '3',
    'amt' => $amt,
  );
  $clientSign = sign($signPayload, $paykey);
  $payload["clientSign"] = $clientSign;
  ksort($payload);
  $client = new Client([
    'base_uri' => 'https://openapi.uepay.mo/unionPayOnLinePay/unifiedorder',
    'timeout'  => 5.0,
  ]);
  $response = $client->request('POST', $UEPAY_URL, [
    'form_params' => $payload
  ]);
  return $response->getBody();
}

function createUrl($body)
{
  return "https://renderer.sum-foods.com//?html=" . base64UrlEncode(utf8($body));
}

function Verify($payload, $uePayKey)
{
  if (empty($payload["sign"])) return false;
  $data = array();
  foreach ($payload as $key => $value) {
    if ($key != "sign") {
      $data[$key] = $value;
    }
  }
  return sign($data, $uePayKey) == $payload["sign"];
}
