<?php

namespace Drupal\uc_interkassa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Drupal\uc_order\Entity\Order;
use \Drupal\Core\Render\Markup;
use Drupal\uc_interkassa\InterkassaPaymentTrait;


class InterkassaAjaxForm extends FormBase {
  use InterkassaPaymentTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'interkass_payment_modal_form';
  }
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $session = \Drupal::service('session');
    $order = Order::load($session->get('order_id'));
    $config = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order)->getConfiguration();

    $content = $this->getPaymentsAPI($config);

    $form['#action'] = Url::fromRoute('uc_interkassa.sendSign')->setAbsolute()->toString();
    $form['#theme'] = 'payways_form';
    $form['payment_metod'] = array('#type' => 'hidden', '#value' => '');
    foreach ($content as $name => $payway) {
      $form['content'][$name]['image'] = [
        '#url' => '/'.drupal_get_path('module', 'uc_interkassa') . '/images/'.$name.'.png',
      ];
      foreach ($payway['currency'] as $currency => $currencyAlias) {
        $form['content'][$name]['currency'][$currencyAlias] = [
          '#title' => $currency,
        ];
      }
      $form['content'][$name]['payment_confirmation'] = [
        '#value' => $this->t($payway['title']),
        '#title' => $this->t('Оплатить через'),
      ];
    }
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }
  public function currencySubmitCallback(array &$form, FormStateInterface $form_state) {
    $trigerName = $form_state->getTriggeringElement()['#name'];
    foreach ($form['content'] as $name => $currencys) {
      foreach ($currencys['currency'] as $key=>$currency) {
        if ($currency['#name'] == $trigerName) {
          $form['content'][$name]['currency'][$key]['#atributes']['class'] = [
            'btn',
            'btn-primary',
            'btn-sm',
            'Active',
          ];
        }
        else {
          $form['content'][$name]['currency'][$key]['#atributes']['class'] = [
            'btn',
            'btn-primary',
            'btn-sm',
            'Active',
          ];
        }
      }
    }
    return $form;
  }
  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
