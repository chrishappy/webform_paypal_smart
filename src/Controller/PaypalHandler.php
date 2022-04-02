<?php

namespace Drupal\webform_paypal_smart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/* Classes used in the __construct */
use Drupal\webform_paypal_smart\WebformPaypalApi;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\webform_paypal_smart\Plugin\WebformHandler\WebformPaypalSmartButtons;
// use Drupal\node\Entity\Node;
// use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaypalHandler extends ControllerBase {
  
  protected $entityTypeManager;
  protected $request;
  protected $webformPaypalApi;
  protected $logger;
    
  /**
   * @return null
   */
  public function __construct(EntityTypeManager $entity_type_manager, WebformPaypalApi $webformPaypalApi, Request $request, LoggerChannelFactoryInterface $logger) {
    $this->webformPaypalApi = $webformPaypalApi;
    $this->request = $request;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get(WebformPaypalApi::LOGGER_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('webform_paypal_api'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('logger.factory')
    );  
  }
  
  public function processOrder() {
    $response = new JsonResponse();
    $response->setMaxAge(0); // No caching
    
    try {
      $orderID = $this->request->request->get('orderID');
      $referenceID = $this->request->request->get('referenceID');

      if (empty($orderID) || empty($referenceID)) {
        if (empty($orderID)) {
          $this->logger->info($this->t('PayPalOrdersApi: ID not found'));
        }
        
        if (empty($referenceID)) {
          $this->logger->info($this->t('PayPalOrdersApi: Webform Reference ID not found'));
        }
        
        throw new \Exception('No data');
      }
      else {

        /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store */
        $temp_store = \Drupal::service('tempstore.private')->get('webform_paypal_smart_buttons');
        $orderMap = $temp_store->get('webformOrders');
        $sid = $orderMap[$referenceID] ?? -1;

        if ($sid <= 0) {
          throw new \Exception('Could not fetch Webform Submission: reference key missing');
        }
        else {
          // Remove record
          unset($orderMap[$referenceID]);
          $temp_store->set('webformOrders', $orderMap);
        }

        $this->logger->info($this->t('PayPalOrdersApi: Processing Order #') . $orderID);

        // Load submission using sid.
        /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
        $webform_submission = $this->entityTypeManager->getStorage('webform_submission')->load($sid);

        if (empty($webform_submission)) {
          throw new \Exception('Could not fetch Webform Submission');
        }

        // Get the current environment
        $handlerCollection = $webform_submission->getWebform()->getHandlers('webform_paypal_smart_buttons_handler');
        $handlers = $handlerCollection->getInstanceIds();
        $webformPaypalSmartHandler = $handlerCollection->get(reset($handlers));
        $paypalEnvironment = $webformPaypalSmartHandler->getSetting(WebformPaypalApi::STATUS_SETTING_NAME);

        // Get the order from PayPal
        $paypalOrder = $this->webformPaypalApi->getOrder($orderID, $paypalEnvironment);
        if (empty($paypalOrder)) {
          throw new \Exception('Could not fetch PayPal order');
        }
        
        // Get paypal details (aka the purchase units)
        $paypalPurchaseUnits = $paypalOrder->result->purchase_units;

        // Check that the reference ids match each other
        if (empty($paypalPurchaseUnits[0]) || $paypalPurchaseUnits[0]->reference_id !== $referenceID) {
          throw new \Exception('Reference values do not match');
        }

        // Validate the paypal total
        $paypalTotalAmount = $this->calcuatePaypalTotalPaid($paypalPurchaseUnits);
        $webformTotal = $this->calcuateWebformTotalPaid($webform_submission->getData());

        // @TODO How to detect that payment didn't change?
        $webform_submission->setElementData('_paypal_order_id', $orderID);
        $webform_submission->setElementData('_paypal_order_json', json_encode($paypalOrder)); // Does not store billing address

        // if ($paypalEnvironment !== WebformPaypalApi::PAYPAL_SANDBOX) {
          $webform_submission->set('in_draft', FALSE); // Transfer webform from 'draft' to 'complete'
          // Set message
          \Drupal::messenger()->addMessage('Your payment has been successfully processed');
        // }
        // else {
        //   \Drupal::messenger()->addWarning('Your payment will be stored, but still draft since still in sandbox mode');
        // }

        $webform_submission->save();

        // Trigger hooks
        $webform_id = $webform_submission->getWebform()->id();
        \Drupal::moduleHandler()->invokeAll('webform_paypal_smart_submission_post_save', [$webform_submission, $webform_id]);
        
        $this->logger->info('PayPalOrdersApi: Finished storing Order #@orderID', ['@orderID' => $orderID]);
        
        $response->setStatusCode(200);
        $response->setData([
          'status' => 200,
          'message' => $this->t('Transaction stored.')
        ]);
        return $response;
      }

    } catch (\BraintreeHttp\HttpException $ex) {
      $this->logger->error("PayPalOrdersApi: There was an error: %ex\n", ['%ex' => json_encode($ex)]);

      $response->setStatusCode(400);
      $response->setData([
        'status' => 400,
        'message' => $this->t('Paypal Issue processing: %ex', ['%ex' => $ex->getMessage()]),
      ]);
      return $response;
      
    } catch (\Exception $ex) {
      $this->logger->error("PayPalOrdersApi: @message <br>\n", ['@message' => $ex->getMessage()]);

      $response->setStatusCode(400);
      $response->setData([
        'status' => 400,
        'message' => $this->t('General Issue processing: %ex', ['%ex' => $ex->getMessage()]),
      ]);
      return $response;
      
    }
  }

  /**
   * Calculate the total the payee paid
   * 
   * @param array|object $paypalPurchaseUnits
   */
  private function calcuatePaypalTotalPaid($paypalPurchaseUnits) {
    $paypalTotalAmount = 0.0;

    if (is_array($paypalPurchaseUnits)) {
      foreach($paypalPurchaseUnits as $paypalDetail) {
        $paypalTotalAmount += (float) $paypalDetail->amount->value;
      }
    }
    else {
      $paypalTotalAmount = (float) $paypalPurchaseUnits->amount->value;
    }

    return $paypalTotalAmount;
  }

  private function calcuateWebformTotalPaid(array $webformData) {
    // $webformData
  }

  /**
   * Break apart the SKU, e.g. paragraph__tickets__123
   */
  // private function processSku($sku) {
  //   $result = explode('__', $sku);
  //   return count($result) == 2 ? $result : false;
  // }

}