<?php
namespace Drupal\webform_paypal_smart\Plugin\WebformHandler;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;

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
  
  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    
    // Set the webform submission id if it exists
    if (!empty($webform_submission->id())) {
      $form['#attributes']['data-sid'] = $webform_submission->id();
    }
    
    // Attach classes + library
    $form['#attributes']['class'][] = 'js--webformPaypalCheckoutForm'; // @TODO make constants in webform api
    $form['#attached']['library'][] = 'webform_paypal_smart/webform-paypal-checkout';
    
    if (isset($form['elements']['actions'])) {
      $form['elements']['actions']['#attributes']['class'][] = 'visually-hidden'; // We want users to click the paypal button
      $form['elements']['actions']['#submit__attributes']['class'][] = 'js--webformPaypalCheckoutSubmitButton'; // @TODO make constants in webform api
    }
    else {
      // @TODO What happens if the user has a different submit button?
    }
  }


  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    
    
  }
}
