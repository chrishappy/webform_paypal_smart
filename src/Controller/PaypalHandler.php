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
      $container->get('logger.factory'),
    );  
  }
  
  public function processOrder() {
    $response = new JsonResponse();
    $response->setMaxAge(0); // No caching
    
    try {
      $orderID = $this->request->request->get('orderID');
      $sid = $this->request->request->get('submissionID');

      if (empty($orderID) || empty($sid)) {
        if (empty($orderID)) {
          $this->logger->info($this->t('PayPalOrdersApi: ID not found'));
        }
        
        if (empty($sid)) {
          $this->logger->info($this->t('PayPalOrdersApi: Webform Submission ID not found'));
        }
        
        $response->setData($this->t('No data'));
        $response->setStatusCode(400);
        return $response;

      }
      else {
        $this->logger->info($this->t('PayPalOrdersApi: Processing Order #') . $orderID);

        $paypalOrder = $this->webformPaypalApi->getOrder($orderID);
        
        if (empty($paypalOrder)) {
          throw new \Exception('Could not fetch PayPal order');
        }
        
        $paypalDetails = $paypalOrder->result->purchase_units;
//        $amount = (float) $paypalDetails->amount->value;

        //@TODO Detect if payment is in real or sandbox (using links array?)
        debug($paypalOrder);
        debug($paypalDetails);
        
        // Load submission using sid.
        /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
        $webform_submission = \Drupal\webform\Entity\WebformSubmission::load($sid);
        $webform_submission->set('_paypal_data', [
          'order_id' => $orderID,
          'payment_json' => json_encode($paypalOrder),
        ]);
        $webform_submission->save();

        //@TODO Process Payment
        
        $this->logger->info('PayPalOrdersApi: Finished storing Order #@orderID', ['@orderID' => $orderID]);
        
        $response->setData($this->t('Transaction stored.'));
        return $response;
      }

    } catch (\BraintreeHttp\HttpException $ex) {
      $this->logger->error("PayPalOrdersApi: There was an error: %ex\n", ['%ex' => json_encode($ex)]);

      $response->setStatusCode(400);
      $response->setData($this->t('Issue processing'));
      return $response;
      
    } catch (\Exception $ex) {
      $this->logger->error("PayPalOrdersApi: @message <br> %ex\n", ['@message' => $ex->getMessage(), '%ex' => json_encode($ex)]);

      $response->setStatusCode(400);
      $response->setData($this->t('Issue processing'));
      return $response;
      
    }

  }

  private function processSku($sku) {
    $result = explode('__', $sku);
    return count($result) == 3 ? $result : false;
  }

}