<?php
namespace Drupal\webform_paypal_smart\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

use Drupal\webform_paypal_smart\WebformPaypalApi;
use Drupal\Component\Utility\Crypt;
use Drupal\webform\Utility\WebformElementHelper;

// use Drupal\Component\Utility\Xss;
// use Drupal\Core\Config\ConfigFactoryInterface;
// use Drupal\Core\Entity\EntityTypeManagerInterface;
// use Drupal\Core\Logger\LoggerChannelFactoryInterface;
// use Drupal\Core\Render\Markup;
// use Drupal\webform\WebformInterface;
// use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
// use Drupal\webform\WebformTokenManagerInterface;
// use Symfony\Component\DependencyInjection\ContainerInterface;


// use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

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

  const CONNECTOR_FIELD_NAME = '_paypal_webform_connector_value';

  /**
   * Paypal Status Options
   */
  public function getPaypalStatusOptions() {
    return [
      WebformPaypalApi::PAYPAL_STATUS_INHERIT         => $this->t('No Override (Inherit from Site Default)'),
      WebformPaypalApi::PAYPAL_SANDBOX                => $this->t('Sandbox Mode'),
      WebformPaypalApi::PAYPAL_PRODUCTION             => $this->t('Live Mode'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $paypalStatusOptions = $this->getPaypalStatusOptions();

    $paypalStatus = $this->getSetting(WebformPaypalApi::STATUS_SETTING_NAME);
    $t_args = [
      '@title' => 'Paypal Status',
      '@status' => $paypalStatusOptions[$paypalStatus],
    ];

    $settings = [
      '#type' => 'markup',
      '#markup' => $this->t('<strong>@title</strong>: @status', $t_args),
    ];

    // return [
    //   '#settings' => $settings,
    // ] + parent::getSummary();
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      WebformPaypalApi::STATUS_SETTING_NAME => WebformPaypalApi::PAYPAL_STATUS_INHERIT,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['paypal_settings'] = [
      '#type' => 'fielset',
      '#tree' => FALSE,
    ];
    
    $form['paypal_settings'][WebformPaypalApi::STATUS_SETTING_NAME] = [
      '#type' => 'radios',
      '#title' => $this->t('PayPal Status Override'),
      '#default_value' => $this->configuration[WebformPaypalApi::STATUS_SETTING_NAME],
      '#options' => $this->getPaypalStatusOptions(),
    ];

    $this->elementTokenValidate($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Completely reset configuration so that custom configuration will always
    // be reset.
    $this->configuration = $this->defaultConfiguration();

    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  
  /**
   * Acts on an webform submission about to be shown on a webform submission form.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param string $operation
   *   The current operation.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function prepareForm(WebformSubmissionInterface $webform_submission, $operation, FormStateInterface $form_state) {

    // Check if the webform has the correct fields
    // TODO: Move to ::buildConfigurationForm() later on
    // TODO: do not allow referenceId to be autocomplete: https://www.drupal.org/project/webform/issues/3114421
    $values = $webform_submission->getWebform()->getElementsDecoded();
    $values = WebformElementHelper::getFlattened($values);
    $requiredFields = array_keys(WebformPaypalApi::FIELD_PAYPAL_LIST);
    foreach ($requiredFields as $requiredField) {
      if (!isset($values[$requiredField])) {
        \Drupal::messenger()->addError($this->t('WebformPaypalSmart: @field is not set on webform', ['@field' => $requiredField]));
      }
    }

    // Set the connect field value
    $referenceId = $webform_submission->getElementData($this::CONNECTOR_FIELD_NAME);
    if (empty($referenceId)) {
      // $values = $form_state->getBuildInfo(); // TODO: can't find build_id
      $value = Crypt::randomBytesBase64(64);
      $webform_submission->setElementData($this::CONNECTOR_FIELD_NAME, $value);
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    
    // Set the webform submission id if it exists
    if (!empty($webform_submission->id())) {
      $form['#attributes']['data-sid'] = $webform_submission->id();
    }

    $form['#attributes']['data-paypal-checkout-webform-id'] = $webform_submission->getWebform()->id();
    
    // Attach classes + library for paypal checkout
    if ($webform_submission->getWebform()->isOpen()) {
      $form['#attributes']['class'][] = 'js--webformPaypalCheckoutForm'; // @TODO make constants in webform api
      $form['#attached']['library'][] = 'webform_paypal_smart/webform-paypal-checkout';
    }

    // Add Paypal SDK depending on Sandbox vs Production
    $paypalStatus = $this->configuration[WebformPaypalApi::STATUS_SETTING_NAME];
    switch ($paypalStatus) {
      case WebformPaypalApi::PAYPAL_STATUS_INHERIT:
        $form['#attached']['library'][] = 'webform_paypal_smart/paypal-sdk';
      break;

      case WebformPaypalApi::PAYPAL_SANDBOX:
        $form['#attached']['library'][] = 'webform_paypal_smart/paypal-sdk--sandbox';
      break;
  
      case WebformPaypalApi::PAYPAL_PRODUCTION:
        $form['#attached']['library'][] = 'webform_paypal_smart/paypal-sdk--live';
      break;
  
      default:
        \Drupal::logger(WebformPaypalApi::LOGGER_NAME)->warning('Paypal Status not set in Webform Paypal (Smart Buttons) settings. Paypal Library could not be generated');
      break;
    }

    // Current user
    $bypassWebformPaymentSmart = \Drupal::currentUser()->hasPermission('bypass webform_paypal_smart payment');
    if (!$bypassWebformPaymentSmart) {
      if (isset($form['actions']) || isset($form['elements']['actions'])) {
        if (isset($form['actions'])) {
          $form['actions']['#attributes']['class'][] = 'hidden'; // We want users to click the paypal button
          $form['actions']['submit']['#access'] = FALSE;
        }

        if (isset($form['elements']['actions']) ){
          $form['elements']['actions']['#attributes']['class'][] = 'hidden'; // We want users to click the paypal button
          $form['elements']['actions']['submit']['#access'] = FALSE;
        }
      }
      else {
        // @TODO What happens if the user has a different submit button?
      }
    }
    else {
      if (isset($form['actions'])) {
        $form['actions']['submit']['#value'] = t('Bypass Payment');
        $form['actions']['draft']['#attributes']['class'][] = 'hidden';
      }

      if (isset($form['elements']['actions']) ){
        $form['elements']['actions']['#submit__label'] = t('Bypass Payment');
        $form['elements']['actions']['#submit__attributes']['class'][] = 'wpb-bypass-payment';
        $form['elements']['actions']['#draft__attributes']['class'][] = 'hidden';
      }
    }
  }

  /* *
   * {@inheritdoc}
   */
  // public function preSave(WebformSubmissionInterface $webform_submission) {
  // }
  
  /* *
   * {@inheritdoc}
   */
  // public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
  //   // @TODO Should we validate the price?

  //   // $tickets = $form_state->getValues()['tickets']
  //   // \Drupal::messenger()->addMessage(var_export(, true));
  //   // debug('validateForm There are ' . count($form_state->getData()['tickets']) . ' tickets');
  //   // $form

  //   parent::submitForm($form, $form_state, $webform_submission);
  // }


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
    // Get the reference id
    $referenceId = $webform_submission->getElementData($this::CONNECTOR_FIELD_NAME);

    // Add it to the temp store
    $tempstore = \Drupal::service('tempstore.private')->get('webform_paypal_smart_buttons');
    $orders = $tempstore->get('webformOrders');
    $orders[$referenceId] = $webform_submission->id();

    $tempstore->set('webformOrders', $orders);

    debug([$tempstore->get('webformOrders'), $webform_submission->id() => $webform_submission->getData()]);
  }

}
