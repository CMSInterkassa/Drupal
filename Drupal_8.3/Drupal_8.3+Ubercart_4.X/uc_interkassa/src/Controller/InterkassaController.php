<?php


namespace Drupal\uc_interkassa\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_interkassa\InterkassaPaymentTrait;
use Symfony\Component\HttpFoundation\Response;


class InterkassaController extends ControllerBase {
  use InterkassaPaymentTrait;

  public function complete(OrderInterface $uc_order) {
    $request = \Drupal::request();
    $session = \Drupal::service('session');
    \Drupal::logger('uc_interkassa')
      ->notice('Получено оповещение о заказе @order_id.', [
        '@order_id' => $uc_order->id()
      ]);
    uc_order_comment_save($uc_order->id(), 0, $this->t('Заказ был сделан на сайте с помощью Интеркасса.'), 'admin');
    if (!$this->checkIP()) {
      uc_order_comment_save($uc_order->id(), 0, $this->t('Не пройдена проверка IP адреса Интеркассы.'), 'admin');
      return array(
        '#theme' => 'uc_cart_complete_sale',
        '#message' => array('#markup' => 'Не пройдена проверка IP адреса Интеркассы.'),
        '#order' => $uc_order,
      );
    }
    $post = $request->request->all();
    if (!isset($post['ik_inv_st'])) {
      uc_order_comment_save($uc_order->id(), 0, $this->t('Не определен статус заказа Интеркассы.'), 'admin');
      return array(
        '#theme' => 'uc_cart_complete_sale',
        '#message' => array('#markup' => 'Не определен статус заказа Интеркассы.'),
        '#order' => $uc_order,
      );
    }
    switch ($post['ik_inv_st']) {
      case 'canceled':
        $uc_order->setStatusId('canceled')->save();
//        $comment = $this->t('Заказ был отменен пользователем');
//        uc_payment_enter($uc_order->id(), 'interkassa', $request->request->get('total'), 0, NULL, $comment);
        $this->messenger()->addStatus($this->t('Заказ был отменен пользователем'));
        uc_order_comment_save($uc_order->id(), 0, $this->t('Заказ не был оплачен с помощью Интеркассы.'), 'admin');
        return array(
          '#theme' => 'uc_cart_complete_sale',
          '#message' => array('#markup' => 'Заказ был отменен пользователем'),
          '#order' => $uc_order,
        );
        break;
      case 'waitAccept':
        if ($uc_order->getStatusId != 'payment_received') {
          $uc_order->setStatusId('pending')->save();
        }
//        $comment = $this->t('Заказ не был оплачен');
//        uc_payment_enter($uc_order->id(), 'interkassa', $request->request->get('total'), 0, NULL, $comment);
        $this->messenger()->addStatus($this->t('Заказ не был оплачен'));
        uc_order_comment_save($uc_order->id(), 0, $this->t('Заказ не был оплачен с помощью Интеркассы.'), 'admin');
        return array(
          '#theme' => 'uc_cart_complete_sale',
          '#message' => array('#markup' => 'Заказ не был оплачен'),
          '#order' => $uc_order,
        );
        break;
      case 'success':
        $session->set('uc_checkout_complete_' . $uc_order->id(), TRUE);
//        $comment = $this->t('Заказ был оплачен, пользователь вернулся на сайт');
//        uc_payment_enter($uc_order->id(), 'interkassa', $request->request->get('total'), 0, NULL, $comment);
        $this->messenger()->addStatus($this->t('Ваш заказ был обработан с помощью Интеркассы'));
        uc_order_comment_save($uc_order->id(), 0, $this->t('Заказ оплачен с помощью Интеркассы.'), 'admin');
        return $this->redirect('uc_cart.checkout_complete');
        break;

    }
    uc_order_comment_save($uc_order->id(), 0, $this->t('Не определен статус заказа Интеркассы.'), 'admin');
    return array(
      '#theme' => 'uc_cart_complete_sale',
      '#message' => array('#markup' => 'Не определен статус заказа Интеркассы.'),
      '#order' => $uc_order,
    );
  }

  public function notification(OrderInterface $uc_order) {
    $request = \Drupal::request();
    $post = $request->request->all();
    \Drupal::logger('uc_interkassa')
      ->notice('Получено оповещение о заказе с следующими данными: @data', ['@data' => print_r($post, TRUE)]);
    $plugin = \Drupal::service('plugin.manager.uc_payment.method')
      ->createFromOrder($uc_order);

    $configuration = $plugin->getConfiguration();
    if (!$this->checkIP()) {
      $uc_order->setStatusId('canceled')->save();
      uc_order_comment_save($uc_order->id(), 0, $this->t('Не пройдена проверка IP адреса Интеркассы.'), 'admin');
      return new Response();
    }
    $sign = $this->createSign($post,$configuration);
    if (!isset($post['ik_sign']) || $post['ik_sign'] != $sign) {
      $uc_order->setStatusId('canceled')->save();
      uc_order_comment_save($uc_order->id(), 0, $this->t('Не пройдена проверка подписи Интеркассы.'), 'admin');
      return new Response();
    }
    if ($uc_order->id() != $post['ik_pm_no']) {
      $uc_order->setStatusId('canceled')->save();
      uc_order_comment_save($uc_order->id(), 0, $this->t('Неверный номер заказа оплаты Интеркассы.'), 'admin');
      return new Response();
    }
    $comment = $this->t('Платеж Интеркассы был проверен, номер заказа: @txn_id', ['@txn_id' => $uc_order->id()]);
    uc_payment_enter($uc_order->id(), 'interkassa', $request->request->get('total'), $uc_order->getOwnerId(), NULL, $comment);
    $uc_order->setStatusId('payment_received')->save();
    return new Response();
  }



 public function sendSign(Request $request) {
    $post = $request->request->all();
    if (isset($post['ik_pm_no']) && $post['ik_pm_no']) {
      $order = Order::load($post['ik_pm_no']);
      $plugin = \Drupal::service('plugin.manager.uc_payment.method')
        ->createFromOrder($order);
      $configuration = $plugin->getConfiguration();
      $sign = $this->createSign($post, $configuration);
      echo $sign;
    }
    exit;
  }
}
