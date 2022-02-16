<?php
namespace Drupal\webform_paypal_smart\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "webform_paypal_smart_buttons_handler",
 *   label = @Translation("Paypal Smart Buttons"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Create Paypal Smart Button order using specified fields"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class WebformPaypalSmartButtons extends WebformHandlerBase {
  
//  private $product;
//  private $custom;
//  private $data;
  
  // /**
  //  * {@inheritdoc}
  //  */
  // public function getSummary() {
  //   return [];
  // }
  
  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    
    // Set the webform submission id if it exists
    if (!empty($webform_submission->id())) {
      $form['#attributes']['data-sid'] = $webform_submission->id();
    }

    $form['#attributes']['data-paypal-checkout-webform-id'] = $webform_submission->getWebform()->id();
    
    // $form['#attributes']['class'][] = $webform_submission->getWebform()->isOpen() ? 'webform-status--open' : 'webform-status--closed'; // @TODO make constants in webform api

    // Attach classes + library for paypal checkout
    if ($webform_submission->getWebform()->isOpen()) {
      $form['#attributes']['class'][] = 'js--webformPaypalCheckoutForm'; // @TODO make constants in webform api
      $form['#attached']['library'][] = 'webform_paypal_smart/webform-paypal-checkout';
    }

    // if ($webform_submission->isNew()) {
      if (isset($form['actions']) || isset($form['elements']['actions'])) {
        if (isset($form['actions'])) {
          $form['actions']['#attributes']['class'][] = 'hidden'; // We want users to click the paypal button
          unset($form['actions']['submit']);
        }

        if (isset($form['elements']['actions']) ){
          $form['elements']['actions']['#attributes']['class'][] = 'hidden'; // We want users to click the paypal button
          unset($form['elements']['actions']['submit']);
        }
      }
      else {
        debug(json_encode($form));
        // @TODO What happens if the user has a different submit button?
      }
    // }
  }
  
  // /**
  //  * {@inheritdoc}
  //  */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // @TODO Should we validate the price?

    // $tickets = $form_state->getValues()['tickets']
    // \Drupal::messenger()->addMessage(var_export(, true));
    // debug('validateForm There are ' . count($form_state->getData()['tickets']) . ' tickets');
    // $form
  }


  /**
   * After a draft is saved, webform temporarily stores the webform information for when the paypal order completes
   * 
   * @TODO Handle case when customer pays for another ticket before closing the paypal window
   * 
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param bool $update
   *   TRUE if the entity has been updated, or FALSE if it has been inserted.
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $tempstore = \Drupal::service('tempstore.private')->get('webform_paypal_smart_buttons');
    $tempstore->set('currentWebformOrder', [
      'sid' => $webform_submission->id(),
      'bundle' =>  $webform_submission->bundle() ?? 'invalid',
    ]);
  }

}
