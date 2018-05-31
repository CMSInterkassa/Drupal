<?php
/**
 * Модуль разработан в компании GateOn предназначен для CMS Drupal 8.2.x + Ubercart 4
 * Сайт разработчикa: www.gateon.net
 * E-mail: www@smartbyte.pro
 * Версия: 1.2
 */
namespace Drupal\uc_interkassa\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;
use Drupal\uc_interkassa\InterkassaPaymentTrait;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Defines the Interkassa payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "interkassa",
 *   name = @Translation("Interkassa"),
 * )
 */
class Interkassa extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface {
  use InterkassaPaymentTrait;
  /**
   * {@inheritdoc}
   */

  public function getDisplayLabel($label) {
    $build['#attached']['library'][] = 'uc_interkassa/interkassa';
    $build['label'] = array(
      '#plain_text' => $label,
      '#suffix' => '<br />',
    );
    $build['image'] = array(
      '#theme' => 'image',
      '#uri' => drupal_get_path('module', 'uc_interkassa') . '/images/interkassa_logo.gif',
      '#alt' => $this->t('Interkassa'),
      '#attributes' => array('class' => array('uc-interkassa-logo')),
    );

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'test_mode' => FALSE,
      'test_key' => '',
      'sid' => '',
      'secret_key' => '',
      'api_mode' => FALSE,
      'api_id' => '',
      'api_key' => '',
      'account_id' => '',
      'server' => 'https://sci.interkassa.com/',
      'hostAccount' => 'https://api.interkassa.com/v1/account',
      'hostPaySystem' => 'https://api.interkassa.com/v1/paysystem-input-payway',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['test_mode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Тестовый режим'),
      '#description' => $this->t('Платежы будут совершенны в тестовом режиме.'),
      '#default_value' => $this->configuration['test_mode'],
    );
    $form['test_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Тестовый ключ'),
      '#description' => $this->t('Находится в настройках кассы "Безопасность" -> "Тестовый ключ".'),
      '#default_value' => $this->configuration['test_key'],
      '#size' => 40,
    );
    $form['sid'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Интеркасса ID'),
      '#description' => $this->t('ID вашей кассы'),
      '#default_value' => $this->configuration['sid'],
      '#size' => 40,
    );
    $form['secret_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Секретный ключ'),
      '#description' => $this->t('Находится в настройках кассы "Безопасность" -> "Секретный ключ".'),
      '#default_value' => $this->configuration['secret_key'],
      '#size' => 40,
    );
    $form['api_mode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Включить API'),
      '#description' => $this->t('Включить API/Выключить Api.'),
      '#default_value' => $this->configuration['api_mode'],
    );
    $form['api_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API Id'),
      '#description' => $this->t('Находится в настройках учетной записи в разделе API.'),
      '#default_value' => $this->configuration['api_id'],
      '#size' => 40,
    );
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Находится в настройках учетной записи в разделе API.'),
      '#default_value' => $this->configuration['api_key'],
      '#size' => 40,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['test_mode'] = $form_state->getValue(['settings','test_mode']);
    $this->configuration['test_key'] = $form_state->getValue(['settings','test_key']);
    $this->configuration['sid'] = $form_state->getValue(['settings','sid']);
    $this->configuration['secret_key'] = $form_state->getValue(['settings','secret_key']);
    $this->configuration['api_mode'] = $form_state->getValue(['settings','api_mode']);
    $this->configuration['api_id'] = $form_state->getValue(['settings','api_id']);
    $this->configuration['api_key'] = $form_state->getValue(['settings','api_key']);
    $this->configuration['account_Id'] = $this->getAccountApi($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
    $data = array(
      'ik_co_id' => $this->configuration['sid'],
      'ik_pm_no' => $order->id(),
      'ik_desc' => '#' . $order->id(),
      'ik_am' => uc_currency_format($order->getTotal(), FALSE, FALSE, '.'),
      'ik_cur' => $order->getCurrency(),
      'ik_ia_u' => Url::fromRoute('uc_interkassa.notification', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_fal_u' => Url::fromRoute('uc_interkassa.complete', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_suc_u' => Url::fromRoute('uc_interkassa.complete', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_pnd_u' => Url::fromRoute('uc_interkassa.complete', ['uc_order' => $order->id()])->setAbsolute()->toString()

    );

    $sign = $this->createSign($data,$this->configuration['secret_key']);

    $data['ik_sign'] = $sign;

    $i = 0;
    foreach ($order->products as $product) {
      $i++;
      $data['li_' . $i . '_type'] = 'product';
      $data['li_' . $i . '_name'] = $product->title->value; // @todo: HTML escape and limit to 128 chars
      $data['li_' . $i . '_quantity'] = $product->qty->value;
      $data['li_' . $i . '_product_id'] = $product->model->value;
      $data['li_' . $i . '_price'] = uc_currency_format($product->price->value, FALSE, FALSE, '.');
    }

    $form['#action'] = $this->configuration['server'];

    foreach ($data as $name => $value) {
      $form[$name] = array('#type' => 'hidden', '#value' => $value);
    }
    $form['actions'] = array('#type' => 'actions');
    if ($this->configuration['api_mode']) {
      $session = \Drupal::service('session');
      $session->set('order_id', $order->id());
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $form['open_modal'] = [
        '#type' => 'link',
        '#title' => $this->t('Выбрать метод оплаты'),
        '#url' => Url::fromRoute('uc_interkassa.open_modal_payment'),
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
        '#value' => $this->t('Submit order'),
        '#attributes' => [
          'style' => [
            'display:none',
          ],
        ],
      );

    } else {
      $form['actions'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Submit order'),
      );
    }

    return $form;
  }

}

