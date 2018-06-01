<?php

namespace Drupal\commerce_payment_ik\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBuilder;
use Drupal\commerce_payment_ik\InterkassaPaymentTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;


class PaymentOffsiteForm extends BasePaymentOffsiteForm {
  use InterkassaPaymentTrait;

  private $pluginConfig;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();


    $this->pluginConfig = $payment_gateway_plugin->getConfiguration();
    $method = 'post';
    $action = $this->pluginConfig['server'];
    $session = \Drupal::service('session');
    $session->set('order_id', $payment->getOrderId());

    $data = array(
      'ik_co_id' => $this->pluginConfig['sid'],
      'ik_am' => number_format($payment->getAmount()->getNumber(), 2, '.', ''),
      'ik_pm_no' => $payment->getOrderId(),
      'ik_desc' => 'Payment for order #: ' . $payment->getOrderId(),
      'ik_cur' => $payment->getAmount()
        ->getCurrencyCode() == 'RUR' ? 'RUB' : $payment->getAmount()
        ->getCurrencyCode(),
      'ik_suc_u' => $form['#return_url'],
      'ik_fal_u' => $form['#cancel_url'],
      'ik_pnd_u' => $form['#return_url'],
      'ik_ia_u' => $base_url . '/payment/notify/interkassa'
    );

    $sign = $this->createSign($data,$this->pluginConfig['secret_key']);

    $data['ik_sign'] = $sign;

    return $this->buildRedirectForm($form, $form_state, $action, $data, $method);
  }

  protected function buildRedirectForm(array $form, FormStateInterface $form_state, $redirect_url, array $data, $redirect_method = self::REDIRECT_GET) {
    if ($this->pluginConfig['api_mode']) {
      $form['#action'] = $redirect_url;
      $form['action'] = [
        '#type' => 'hidden',
        '#value' => $redirect_url,
        '#parents' => ['action'],
      ];
      $form['#process'] = [
        [get_class($this), 'processRedirectForm'],
      ];
      foreach ($data as $key => $value) {
        $form[$key] = [
          '#type' => 'hidden',
          '#value' => $value,
          // Ensure the correct keys by sending values from the form root.
          '#parents' => [$key],
        ];
      }


      $form['#attached']['library'][] = 'commerce_payment_ik/interkassa';
      $form['open_modal'] = [
        '#type' => 'link',
        '#title' => t('Выбрать метод оплаты'),
        '#url' => Url::fromRoute('commerce_payment_ik.open_modal_payment'),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
          ],
          'data-dialog-type' => 'modal',
        ],
      ];
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit order'),
        '#action' => $redirect_url,
        '#attributes' => [
          'style' => [
            'display:none',
          ],
        ],
      );
    }
    else {
      $form = parent::buildRedirectForm($form, $form_state, $redirect_url, $data, $redirect_method);
    }

    return $form;
  }
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $modal_form = \Drupal::formBuilder()->getForm('Drupal\commerce_payment_ik\Form\InterkassaAjaxForm', $this->pluginConfig);
    $response->addCommand(new OpenModalDialogCommand("", $modal_form, ['width' => 'auto','height' => 'auto']));
    return $response;
  }


}
