<?php

namespace Drupal\webform_paypal_smart;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;

use Drupal\webform_paypal_smart\WebformPaypalApi;

ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

class PayPalClient
{
  
  /**
   * Returns PayPal HTTP client instance with environment which has access
   * credentials context. This can be used invoke PayPal API's provided the
   * credentials have the access to do so.
   */
  public static function client(string $environment_type = NULL)
  {
     if (!class_exists('PayPalCheckoutSdk\Core\PayPalHttpClient')) {
       throw new \Exception('Missing a class: PayPalHttpClient');
     }
    
      return new PayPalHttpClient(self::environment($environment_type));
  }

  /**
   * Setting up and Returns PayPal SDK environment with PayPal Access credentials.
   * For demo purpose, we are using SandboxEnvironment. In production this will be
   * ProductionEnvironment.
   */
  public static function environment(string $environment_type = NULL)
  {
    $paypalConfig = \Drupal::config(WebformPaypalApi::PAYMENT_CONFIG);
    
    if ($environment_type === NULL || $environment_type == WebformPaypalApi::PAYPAL_STATUS_INHERIT) {
      $environment_type = $paypalConfig->get(WebformPaypalApi::STATUS_SETTING_NAME);
    }

    switch ($environment_type) {
      case WebformPaypalApi::PAYPAL_SANDBOX:
      default: // Default is Sandbox mode
        $userClientId = $paypalConfig->get('paypal_sandbox_client_id');
        $userClientSecret = $paypalConfig->get('paypal_sandbox_secret');

        if ( empty($userClientId) || empty($userClientSecret) ) {
          \Drupal::logger(WebformPaypalApi::LOGGER_NAME)->warning('Paypal Sandbox Client ID or Secret not set');
        }

        $clientId = getenv("CLIENT_ID") ?: $userClientId;
        $clientSecret = getenv("CLIENT_SECRET") ?: $userClientSecret;
        return new SandboxEnvironment($clientId, $clientSecret);
        break;

      // Production mode can be set explicitly in Webform Paypal UI.
      case WebformPaypalApi::PAYPAL_PRODUCTION:
        $userClientId = $paypalConfig->get('paypal_live_client_id');
        $userClientSecret = $paypalConfig->get('paypal_live_secret');

        if ( empty($userClientId) || empty($userClientSecret) ) {
          \Drupal::logger(WebformPaypalApi::LOGGER_NAME)->warning('Paypal Live Client ID or Secret not set');
        }

        $clientId = getenv("CLIENT_ID") ?: $userClientId;
        $clientSecret = getenv("CLIENT_SECRET") ?: $userClientSecret;

        return new ProductionEnvironment($clientId, $clientSecret);
        break;
    }
  }
}
