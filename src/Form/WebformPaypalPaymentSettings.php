<?php
/**
 * @file
 * Contains \Drupal\webform_paypal_smart\Form\WebformPaypalPaymentSettings. 
 */

namespace Drupal\webform_paypal_smart\Form;

use Drupal\Core\Form\ConfigFormBase;  
use Drupal\Core\Form\FormStateInterface; 
use Drupal\webform_paypal_smart\WebformPaypalApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;

class WebformPaypalPaymentSettings extends ConfigFormBase  {

  /**
    * @var \Drupal\Core\Cache\CacheBackendInterface $cacheRender
    */
  protected $cacheRender;

  /**
   * An array of configuration names that should be editable.
   *
   * @var array
   */
  protected $editableConfig = [];
  
  /**
    * Constructs a PerformanceForm object.
    */
  public function __construct(CacheBackendInterface $cacheRender){
    $this->cacheRender = $cacheRender;

    $this->formConfig = WebformPaypalApi::PAYMENT_CONFIG;
    $this->editableConfig[] = $this->formConfig;
  }

  /**
    * {@inheritdoc}
    */
  public static function create(ContainerInterface $container) {
    return new static(
      // $container->get('webform_paypal_smart'), @TODO include later, or remove?
      $container->get('cache.render')
    );
  }
  /**  
   * {@inheritdoc}  
   */  
  protected function getEditableConfigNames() {  
    return $this->editableConfig;
  }  
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_paypal_smart_payment_settings';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = null) {
    
    $config = $this->config($this->formConfig);
    
    /**
     * PayPal API keys
     */
    $form['paypal_api_keys'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('PayPal API'),
    ];

    $form['paypal_api_keys']['paypal_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('PayPal Status'),
      '#default_value' => empty($config->get('paypal_status')) ? 0 : $config->get('paypal_status'),
      '#options' => [
        $this->webformPaypalApi::PAYPAL_SANDBOX         => $this->t('Sandbox'),
        $this->webformPaypalApi::PAYPAL_PRODUCTION      => $this->t('Live'),
      ],
    ];

    // Production keys
    $form['paypal_api_keys']['paypal_live'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Live API Keys'),
      '#states' => [
        'visible' => [
          ':input[name="paypal_status"]' => ['value' => $this->webformPaypalApi::PAYPAL_PRODUCTION],
        ],
      ],
    ];

    $form['paypal_api_keys']['paypal_live']['paypal_live_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('paypal_live_client_id'),
      '#states' => [
        'required' => [
          ':input[name="paypal_status"]' => ['value' => $this->webformPaypalApi::PAYPAL_PRODUCTION],
        ],
      ],
    ];

    $form['paypal_api_keys']['paypal_live']['paypal_live_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#default_value' => $config->get('paypal_live_secret'),
      '#states' => [
        'required' => [
          ':input[name="paypal_status"]' => ['value' => $this->webformPaypalApi::PAYPAL_PRODUCTION],
        ],
      ],
    ];

    // Sandbox keys
    $form['paypal_api_keys']['paypal_sandbox'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sandbox API Keys'),
      '#states' => [
        'visible' => [
          ':input[name="paypal_status"]' => ['value' => $this->webformPaypalApi::PAYPAL_SANDBOX],
        ],
      ],
    ];

    $form['paypal_api_keys']['paypal_sandbox']['paypal_sandbox_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('paypal_sandbox_client_id'),
      '#states' => [
        'required' => [
          ':input[name="paypal_status"]' => ['value' => $this->webformPaypalApi::PAYPAL_SANDBOX],
        ],
      ],
    ];

    $form['paypal_api_keys']['paypal_sandbox']['paypal_sandbox_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#default_value' => $config->get('paypal_sandbox_secret'),
      '#states' => [
        'required' => [
          ':input[name="paypal_status"]' => ['value' => $this->webformPaypalApi::PAYPAL_SANDBOX],
        ],
      ],
    ];

    /**
     * Actions
     */
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;  
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Ensure keys are set
    switch ($values['paypal_status']) {
      case $this->webformPaypalApi::PAYPAL_SANDBOX:
        if ( empty($values['paypal_sandbox_client_id']) ){
          $form_state->setError($form['paypal_api_keys']['paypal_sandbox']['paypal_sandbox_client_id'], $this->t('PayPal Sandbox Client ID cannot be empty'));
        }

        if ( empty($values['paypal_sandbox_secret']) ){
          $form_state->setError($form['paypal_api_keys']['paypal_sandbox']['paypal_sandbox_secret'], $this->t('PayPal Sandbox Secret cannot be empty'));
        }
        break;

      case $this->webformPaypalApi::PAYPAL_PRODUCTION:
        // $config->set( 'paypal_client_id', $config->get( 'paypal_live_client_id' ) );
        // $config->set( 'paypal_secret', $config->get( 'paypal_live_secret' ) );
        if ( empty($values['paypal_live_client_id']) ){
          $form_state->setError($form['paypal_api_keys']['paypal_live']['paypal_live_client_id'], $this->t('PayPal Production Client ID cannot be empty'));
        }

        if ( empty($values['paypal_live_secret']) ){
          $form_state->setError($form['paypal_api_keys']['paypal_live']['paypal_live_secret'], $this->t('PayPal Production Secret cannot be empty'));
        }
        break;
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);  

    $values = $form_state->getValues();
    $config = $this->config($this->formConfig);

    foreach ($values as $key => $value) {
      switch ($key) {

        case 'paypal_live_client_id':
        case 'paypal_live_secret':
        case 'paypal_sandbox_client_id':
        case 'paypal_sandbox_secret':
          // Rebuild cache if any key was changed
          if ( $config->get($key) != $value ) {
            $rebuildConfigCache = true;
          }

          $config->set( $key, $value );
        break;

        case 'paypal_status':
          $config->set( $key, $value);
        break;
      }
    }

    if ($rebuildConfigCache) {
      $this->messenger()->addMessage('Configuation cache cleared since PayPal Api Keys were changed');
      // $this->cacheRender->invalidateMultiple(['cache.config']);
      $this->cacheRender->invalidateAll(); //@todo: only invadiate what is necessary
    }

    $config->save();  
   }
  
}

