<?php

namespace Drupal\commerce_payment_ik\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderStorage;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment_ik\InterkassaPaymentTrait;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "ik_commerce",
 *   label = "Interkassa",
 *   display_label = "Interkassa",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_payment_ik\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 * )
 */
class OffsiteRedirect extends OffsitePaymentGatewayBase {
  use InterkassaPaymentTrait;

  public function defaultConfiguration() {
    return [
      'sid' => '',
      'secret_key' => '',
      'test_key' => '',
      'api_mode' => FALSE,
      'api_id' => '',
      'api_key' => '',
      'account_id' => '',
      'server' => 'https://sci.interkassa.com/',
      'hostAccount' => 'https://api.interkassa.com/v1/account',
      'hostPaySystem' => 'https://api.interkassa.com/v1/paysystem-input-payway',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

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
    $form['test_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Тестовый ключ'),
      '#description' => $this->t('Находится в настройках кассы "Безопасность" -> "Тестовый ключ".'),
      '#default_value' => $this->configuration['test_key'],
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
    parent::submitConfigurationForm($form, $form_state);
    $this->setConfiguration($this->configuration);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['sid'] = $values['sid'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['test_key'] = $values['test_key'];
      $this->configuration['api_mode'] = $values['api_mode'];
      $this->configuration['api_id'] = $values['api_id'];
      $this->configuration['api_key'] = $values['api_key'];
      $this->configuration['account_Id'] = $this->getAccountApi($this->configuration);
    }
  }

  /**
   * PayURL handler
   * URL: http://site.domain/payment/notify/interkassa
   *
   * {@inheritdoc}
   *
   * @param Request $request
   * @return null|void
   */
  public function onNotify(Request $request) {
    try {
      $post = $request->request->all();
      $ik_co_id = $this->configuration['sid'];
      \Drupal::logger('uc_interkassa')
        ->notice('Получено оповещение о заказе с следующими данными: @data', ['@data' => print_r($post, TRUE)]);
      if ($request && $this->checkIP() && $ik_co_id == $post['ik_co_id']) {
        $order_storage = $this->entityTypeManager->getStorage('commerce_order');
        $order = $order_storage->load($post['ik_pm_no']);
        if (is_null($order)) {
          throw new \Exception('Order not found');
        }
        // Сравниваем суммы (принятую и записанную в ORDER'е)
        $amount = number_format($order->getTotalPrice()
          ->getNumber(), 2, '.', '');
        if ($post['ik_am'] < $amount) {
          throw new \Exception('Payment amount mismatch');
        }
        $request_sign = $post['ik_sign'];
        if (isset($post['ik_pw_via']) && $post['ik_pw_via'] == 'test_interkassa_test_xts') {
          $key = $this->configuration['test_key'];
          $test = true;
        }
        else {
          $key = $this->configuration['secret_key'];
          $test = false;
        }
        $sign = $this->createSign($post,$key);
        if ($request_sign == $sign) {
          \Drupal::logger('uc_interkassa')
            ->notice('sign ok.');
          // Меняем статус ORDER'а и сохраняем его
          $order->set('state', 'completed');
          $order->save();
          // Создаём платёж и сохраняем его
          $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
          $payment = $payment_storage->create([
            'state' => 'completed',
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $this->parentEntity->id(),
            'order_id' => $order->id(),
            'test' => $test,
            'remote_id' => $post['ik_pm_no'],
            'remote_state' => 'PAYED',
            'authorized' => \Drupal::time()->getRequestTime(),
          ]);
          $payment->save();
          // Если не произошло исключений, выдаём "SUCCESS"
          echo 'OK';
        }
        else {
          throw new \Exception('Payment signature mismatch');
        }
      }
      else {
        throw new \Exception('Request parameters is not correct');
      }

    } catch (\Exception $e) {
      watchdog_exception('PayURL handler', $e);
      echo 'FAIL';
    }
  }

  /**
   * Page for success
   *
   * {@inheritdoc}
   *
   * @param OrderInterface $order
   * @param Request $request
   */
  public function onReturn(OrderInterface $order, Request $request) {
    \Drupal::messenger()->addStatus('Payment was processed');
  }
}
