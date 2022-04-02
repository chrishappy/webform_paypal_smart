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
    *
    * Strings because config retrieves ints as strings
    */
  // TODO: Reset to actual strings
  // TODO: change names to PAYPAL_STATUS_**
  const PAYPAL_SANDBOX = '0';
  const PAYPAL_PRODUCTION = '1';
  const PAYPAL_STATUS_INHERIT = '__INHERIT_STATUS';

  /**
   * The config setting of the status field
   */
  const STATUS_SETTING_NAME = 'paypal_status';

  /**
   * The webform field used to store Paypal's order id
   */
  const FIELD_PAYPAL_ORDER_ID = '_paypal_order_id';

  /**
   * The webform field used to store the order json from Paypal API
   */
  const FIELD_PAYPAL_ORDER_JSON = '_paypal_order_json';

  /**
   * The webform field used to store the order reference (associate webform with paypal)
   */
  const FIELD_PAYPAL_ORDER_REFERENCE = '_paypal_webform_connector_value';

  /**
   * Required webform fields
   */
  const FIELD_PAYPAL_LIST = [
    self::FIELD_PAYPAL_ORDER_ID => 'value',
    self::FIELD_PAYPAL_ORDER_JSON => 'value',
    self::FIELD_PAYPAL_ORDER_REFERENCE => 'hidden',
  ];

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
   * @return boolean | object
   */
  public static function getOrder($orderId, $environment=NULL) {

    $client = PayPalClient::client($environment);
    $response = $client->execute(new OrdersGetRequest($orderId));
    
    return $response;
  }

  /**
   * Function to determine whether current user can bypass payment
   */
  public static function currentUserCanBypassPayment($currentUser = NULL) {
    $currentUser = $currentUser ?? \Drupal::currentUser();
    $bypassWebformPaymentSmart = $currentUser->hasPermission('bypass webform_paypal_smart payment');

    return $bypassWebformPaymentSmart;
  }
  
}