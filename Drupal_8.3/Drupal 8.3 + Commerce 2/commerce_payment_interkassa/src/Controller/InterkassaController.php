<?php


namespace Drupal\commerce_payment_ik\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment_ik\InterkassaPaymentTrait;
use Drupal\Core\Config;
use Drupal\commerce_order\Entity\Order;


class InterkassaController extends ControllerBase {
  use InterkassaPaymentTrait;
  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;
  /**
   * The ModalFormExampleController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }
  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }
  public function open_modal_payment() {
    $response = new AjaxResponse();
    $session = \Drupal::service('session');
    $orderNum = $session->get('order_id');
    if (isset($orderNum) && $orderNum) {
      $order = Order::load($orderNum);
      $payment_gateway_plugin = $order->get('payment_gateway')->entity->getPlugin();

      $configuration = $payment_gateway_plugin->getConfiguration();
      $modal_form = $this->formBuilder->getForm('Drupal\commerce_payment_ik\Form\InterkassaAjaxForm', $configuration);
      $response->addCommand(new OpenModalDialogCommand("", $modal_form, [
        'width' => 'auto',
        'height' => 'auto'
      ]));
    }
    return $response;
  }
   public function sendSign(Request $request) {
      $post = $request->request->all();
      if (isset($post['ik_pm_no']) && $post['ik_pm_no']) {
        $order = Order::load($post['ik_pm_no']);
        $payment_gateway_plugin = $order->get('payment_gateway')->entity->getPlugin();
        $configuration = $payment_gateway_plugin->getConfiguration();

        $sign = $this->createSign($post, $configuration['secret_key']);
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        echo $sign;
      }
      exit;
  }
}
