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
    
    // if (!empty($form_state->getValues()['tickets']))
    //   \Drupal::messenger()->addMessage('alterForm: There are ' . count($form_state->getValues()['tickets']) . ' tickets');
    
    // Set the webform submission id if it exists
    if (!empty($webform_submission->id())) {
      $form['#attributes']['data-sid'] = $webform_submission->id();
    }
    
    // $form['#attributes']['class'][] = $webform_submission->getWebform()->isOpen() ? 'webform-status--open' : 'webform-status--closed'; // @TODO make constants in webform api

    // Attach classes + library for paypal checkout
    if ($webform_submission->getWebform()->isOpen()) {
      $form['#attributes']['class'][] = 'js--webformPaypalCheckoutForm'; // @TODO make constants in webform api
      $form['#attached']['library'][] = 'webform_paypal_smart/webform-paypal-checkout';
    }
    
    if (isset($form['elements']['actions'])) {
      $form['elements']['actions']['#attributes']['class'][] = 'visually-hidden'; // We want users to click the paypal button
      // $form['elements']['actions']['#submit__attributes']['class'][] = 'js--webformPaypalCheckoutSubmitButton'; // @TODO make constants in webform api

      unset($form['elements']['actions']['submit']);
    }
    else {
      // @TODO What happens if the user has a different submit button?
    }
  }
  
  // /**
  //  * {@inheritdoc}
  //  */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // $tickets = $form_state->getValues()['tickets']
    // \Drupal::messenger()->addMessage(var_export(, true));
    // debug('validateForm There are ' . count($form_state->getData()['tickets']) . ' tickets');
    // $form
  }


  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $tempstore = \Drupal::service('tempstore.private')->get('webform_paypal_smart_buttons');
    $tempstore->set('currentWebformOrder', [
      'sid' => $webform_submission->id(),
      'bundle' =>  $webform_submission->bundle() ?? 'invalid',
    ]);
  }

}
