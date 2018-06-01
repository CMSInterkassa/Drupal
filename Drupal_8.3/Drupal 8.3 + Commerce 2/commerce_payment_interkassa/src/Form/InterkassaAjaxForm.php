<?php

namespace Drupal\commerce_payment_ik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce_payment_ik\InterkassaPaymentTrait;


class InterkassaAjaxForm extends FormBase {
  use InterkassaPaymentTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'interkass_payment_modal_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {

    $config = $options;


    $content = $this->getPaymentsAPI($config);

    $form['#action'] = Url::fromRoute('commerce_payment_ik.sendSign')->setAbsolute()
      ->toString();
    $form['#theme'] = 'payways_form';
    $form['payment_metod'] = array('#type' => 'hidden', '#value' => '');
    foreach ($content as $name => $payway) {
      $form['content'][$name]['image'] = [
        '#url' => '/' . drupal_get_path('module', 'commerce_payment_ik') . '/images/' . $name . '.png',
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}
}
