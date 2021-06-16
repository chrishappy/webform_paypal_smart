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
    // Attach classes + library
    $form['#attributes']['class'][] = 'js--webformPaypalCheckoutForm';
    $form['#attached']['library'][] = 'webform-paypal-smart/webform-paypal-checkout';
    
    if (isset($form['elements']['actions'], $form['elements']['actions']['submit'])) {
      $form['elements']['actions']['submit']['#attributes']['class'][] = 'js--webformPaypalCheckoutSubmitButton';
    }
    else {
      // @TODO What happens if the user has a different submit button?
    }
  }


  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $errorCount = 0;
    
    $node = $webform_submission->getSourceEntity();
    
    $formData = $webform_submission->getData();
    
    $ticketsData = json_decode($form['#attributes']['data-ticket-data'], true);
    $ticketKeys = array_keys($ticketsData);
    
    foreach ($formData['tickets'] as $delta => $ticket) {
      if (!in_array($ticket['ticket_type'], $ticketKeys)) {
        $form_state->setError($form['elements']['tickets']['items'][$delta]['ticket_type'], $this->t('Sorry, this is an unknown ticket'));
        
        // This also works, but PHP will output an error if the above element does not work
        // $form_state->setErrorByName("tickets][items][$delta][ticket_type", $this->t('Sorry, this is an unknown ticket2'));
        $errorCount++;
      }
    }
    
    return ($errorCount === 0);
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    
    // Set Data and save webform
//    $fields = $webform_submission->getData();
//    
//    $webform_submission->setData(array_merge($fields, $this->data));
//    
//    $webform_submission->save();
//    
//    // Get sid
//    $this->sid = $webform_submission->id();
//    
//    // Create Urls
//    $return = Url::fromRoute('<current>',
//                             [],
//                             ['query' => ['paypalReturn' => 'true'], 'absolute' => true]);
//    
//    $cancel_return = Url::fromRoute('<current>',
//                             [],
//                             ['query' => ['paypalReturn' => 'false'], 'absolute' => true]);
//    
//    $notify = Url::fromRoute('cworks.paypal_receive',
//                             ['id' => $this->sid],
//                             ['absolute' => true]);
//    
//    // Data to be used in the PaypayHandler Controller
//    $data = [
//      //'formAction'      => 'https://www.sandbox.paypal.com/cgi-bin/webscr',
//      'formAction'      => 'https://www.paypal.com/cgi-bin/webscr',
//      'fields'          => $fields,
//      'notify'          => $notify->toString(),
//      'return'          => $return->toString(),
//      'cancel_return'   => $cancel_return->toString(),
//      'product'         => $this->product,
//      'custom'          => json_encode($this->custom),
//    ];
//    
//    // Set data according to session id 
//    $_SESSION['webform_data'][$this->sid] = $data;
//
//    $message = 'ID: ' . $this->sid . ':' . print_r($data,1); 
//    \Drupal::logger('SendToPaypal')->debug($message);
//    
//    $form_state->setRedirect('cworks.paypal_send', ['id' => $this->sid]);
  }
}
