<?php

namespace Drupal\webform_paypal_smart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/* Classes used in the __construct */
use Drupal\webform_paypal_smart\WebformPaypalApi;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;

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
      $sid = $this->request->request->get('submissionID');
      debug($this->request->request->all());

      if (empty($orderID) || empty($sid)) {
        if (empty($orderID)) {
          $this->logger->info($this->t('PayPalOrdersApi: ID not found'));
        }
        
        if (empty($sid)) {
          $this->logger->info($this->t('PayPalOrdersApi: Webform Submission ID not found'));
        }
        
        
        throw new \Exception('No data');
      }
      else {
        $this->logger->info($this->t('PayPalOrdersApi: Processing Order #') . $orderID);

        $paypalOrder = $this->webformPaypalApi->getOrder($orderID);

        if (empty($paypalOrder)) {
          throw new \Exception('Could not fetch PayPal order');
        }
        
        // Get paypal details
        $paypalDetails = $paypalOrder->result->purchase_units;

        
        // Load submission using sid.
        /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
        $webform_submission = \Drupal\webform\Entity\WebformSubmission::load($sid);

        // Validate the paypal total
        $paypalTotalAmount = $this->calcuatePaypalTotalPaid($paypalDetails);
        $webformTotal = $this->calcuateWebformTotalPaid($webform_submission->getData());

        // @TODO How to detect that payment didn't change?

        //@TODO Detect if payment is in real or sandbox (using links array?)
        debug($paypalOrder);
        debug($paypalDetails);
        
        $webform_submission->setElementData('_paypal_data', [[
          'order_id' => $orderID,
          'payment_json' => json_encode($paypalOrder),
        ]]);
        $webform_submission->set('in_draft', FALSE); // Transfer webform from 'draft' to 'complete'
        $webform_submission->save();
        
        // Set message
        \Drupal::messenger()->addMessage('Your payment has been successfully proccessed');
        
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
   * @param array|object $paypalDetails
   */
  private function calcuatePaypalTotalPaid($paypalDetails) {
    $paypalTotalAmount = 0.0;

    if (is_array($paypalDetails)) {
      foreach($paypalDetails as $paypalDetail) {
        $paypalTotalAmount += (float) $paypalDetail->amount->value;
      }
    }
    else {
      $paypalTotalAmount = (float) $paypalDetails->amount->value;
    }

    return $paypalTotalAmount;
  }

  private function calcuateWebformTotalPaid(array $webformData) {
    // $webformData
  }

  /**
   * Break apart the SKU, e.g. paragraph__tickets__123
   */
  private function processSku($sku) {
    $result = explode('__', $sku);
    return count($result) == 3 ? $result : false;
  }

}