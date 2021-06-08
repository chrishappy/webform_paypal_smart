<?php

/**
 * @file
 * Contains the webform_paypal_smart service
 */

namespace Drupal\webform_paypal_smart;

use Drupal\webform_paypal_smart\PayPalClient;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class WebformPaypalApi.
 *
 * @package Drupal\WebformPaypalApi
 */
class WebformPaypalApi {
  
  /**
    * Contants for which Paypal Keys to use
    */
  const PAYPAL_SANDBOX = 0;
  const PAYPAL_PRODUCTION = 1;

  /**
   * Used for logging messages
   * e.g. \Drupal::logger(WebformPaypalApi::LOGGER_NAME)
   */
  const LOGGER_NAME = 'Webform Paypal';

  /**
   * Payment settings config key
   * e.g. \Drupal::config(WebformPaypalApi::PAYMENT_CONFIG)
   */
  const PAYMENT_CONFIG = 'webform_paypal_smart.payment_settings';
  
  /**
   * Public function to Call PayPal to get the transaction details
   *
   * @return boolean | array
   */
  public function getOrder($orderId) {
    $client = PayPalClient::client();
    $response = $client->execute(new OrdersGetRequest($orderId));
    
    return $response;
  }
  
}