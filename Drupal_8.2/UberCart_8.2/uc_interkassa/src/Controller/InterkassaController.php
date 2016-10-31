<?php

/**
 * Модуль разработан в компании GateOn предназначен для CMS Drupal 8.2.x + Ubercart 4
 * Сайт разработчикa: www.gateon.net
 * E-mail: www@smartbyte.pro
 * Версия: 1.2
 */

namespace Drupal\uc_interkassa\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_cart\CartManagerInterface;
use Drupal\uc_order\Entity\Order;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


class InterkassaController extends ControllerBase
{

    /**
     * @var \Drupal\uc_cart\CartManager
     */
    protected $cartManager;

    /**
     * @param \Drupal\uc_cart\CartManagerInterface $cart_manager
     *   The cart manager.
     */
    public function __construct(CartManagerInterface $cart_manager)
    {
        $this->cartManager = $cart_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('uc_cart.manager')
        );
    }

    public function complete($cart_id = 0, Request $request)
    {

        \Drupal::logger('uc_interkassa')->notice('Получено оповещение о заказе @order_id.', ['@order_id' => SafeMarkup::checkPlain
        ($request->request->get('ik_pm_no'))]);

        $this->wrlog('complete');

        $request_order_id = $request->request->get('ik_pm_no');
        $order = Order::load($request_order_id);

        $plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);

        $configuration = $plugin->getConfiguration();

        $address = $order->getAddress('billing');
        $address->street1 = $request->request->get('street_address');
        $address->street2 = $request->request->get('street_address2');
        $address->city = $request->request->get('city');
        $address->postal_code = $request->request->get('zip');
        $address->phone = $request->request->get('phone');
        $address->zone = $request->request->get('state');
        $address->country = $request->request->get('country');
        $order->setAddress('billing', $address);
        $order->setPaymentMethodId($order->getPaymentMethodId());

        uc_order_comment_save($order->id(), 0, $this->t('Заказ был сделан на сайте с помощью Интеркасса.'), 'admin');

        switch ($request->request->get('ik_inv_st')) {
            case 'canceled':
                $order->setStatusId('canceled')->save();
                $comment = $this->t('Заказ был отменен пользователем');
                uc_payment_enter($order->id(), 'interkassa', $request->request->get('total'), 0, NULL, $comment);
                drupal_set_message($this->t('Заказ был отменен пользователем'));
                uc_order_comment_save($order->id(), 0, $this->t('Заказ не был оплачен с помощью Интеркассы.'), 'admin');
                return array(
                    '#theme' => 'uc_cart_complete_sale',
                    '#message' => array('#markup' => 'Заказ был отменен пользователем'),
                    '#order' => $order,
                );
                break;
            case 'waitAccept':
                $order->setStatusId('pending')->save();
                $comment = $this->t('Заказ не был оплачен');
                uc_payment_enter($order->id(), 'interkassa', $request->request->get('total'), 0, NULL, $comment);
                drupal_set_message($this->t('Заказ не был оплачен'));
                uc_order_comment_save($order->id(), 0, $this->t('Заказ не был оплачен с помощью Интеркассы.'), 'admin');
                return array(
                    '#theme' => 'uc_cart_complete_sale',
                    '#message' => array('#markup' => 'Заказ не был оплачен'),
                    '#order' => $order,
                );
                break;
            case 'success':
                $comment = $this->t('Заказ был оплачен, пользователь вернулся на сайт');
                uc_payment_enter($order->id(), 'interkassa', $request->request->get('total'), 0, NULL, $comment);
                drupal_set_message($this->t('Ваш заказ был обработан с помощью Интеркассы'));
                uc_order_comment_save($order->id(), 0, $this->t('Заказ оплачен с помощью Интеркассы.'), 'admin');
               return $this->cartManager->completeSale($order);
                break;

        }
    }

    public function notification(Request $request)
    {
        if($this->checkIP()) {

            $this->wrlog('IP ok');

            $values = $request->request;
            \Drupal::logger('uc_interkassa')->notice('Получено оповещение о заказе с следующими данными: @data', ['@data' => print_r($values->all(), TRUE)]);

            $this->wrlog('notification');
        $this->wrlog('#####################START#####################');
        $this->wrlog($values);
        $this->wrlog('######################END#######################');

            $request_order_id = $values->get('ik_pm_no');

            if (isset($request_order_id)) {

                $this->wrlog('order id ok');

                $order_id = $values->get('ik_pm_no');

                if ($request_order_id == $order_id) {

                    $this->wrlog('order id match');

                    $order = Order::load($order_id);

                    $plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);

                    $configuration = $plugin->getConfiguration();

                    $this->wrlog($values->get('ik_inv_st'));

                    if ($values->get('ik_inv_st') == 'success') {

                        $this->wrlog('success');

                        if ($values->get('ik_pw_via') && $values->get('ik_pw_via') == 'test_interkassa_test_xts') {
                            $secret_key = $configuration['test_key'];
                        } else {
                            $secret_key = $configuration['secret_key'];
                        }

                        $request_sign = $values->get('ik_sign');

                        $dataSet = [];

                        foreach ($values as $key => $value) {
                            if (!preg_match('/ik_/', $key)) continue;
                            $dataSet[$key] = $value;
                        }

                        unset($dataSet['ik_sign']);
                        ksort($dataSet, SORT_STRING);
                        array_push($dataSet, $secret_key);
                        $signString = implode(':', $dataSet);
                        $sign = base64_encode(md5($signString, true));


                        if ($request_sign != $sign) {
                            \Drupal::logger('uc_interkassa')->notice('Подписи не совпадают!: @data', ['@data' => print_r($values->get('ik_sign'), TRUE)]);
                            $this->wrlog('Подписи не совпадают!');
                            $order->setStatusId('canceled')->save();
                            die('Hash Incorrect');

                        } else {
                            $this->wrlog('Подписи совпадают!');
                            $order->setStatusId('payment_received')->save();
                        }
                    }

                } else {

                    $this->wrlog('params didnt match');
                }
                $this->complete(1, $request);
            }else{
                $this->wrlog('no order id');
            }
        }else{
            $this->wrlog('REQUEST_IP'.$_SERVER['REMOTE_ADDR'].'doesnt match');
        }
    }
    public function checkIP(){
        $ip_stack = array(
            'ip_begin'=>'151.80.190.97',
            'ip_end'=>'151.80.190.104'
        );

        if(!ip2long($_SERVER['REMOTE_ADDR'])>=ip2long($ip_stack['ip_begin']) && !ip2long($_SERVER['REMOTE_ADDR'])<=ip2long($ip_stack['ip_end'])){
            $this->wrlog('REQUEST IP'.$_SERVER['REMOTE_ADDR'].'doesnt match');
            die('Ты мошенник! Пшел вон отсюда!');
        }
        return true;
    }

    function wrlog($content){
        $file = 'log.txt';
        $doc = fopen($file, 'a');

        file_put_contents($file, PHP_EOL .'===================='.date("H:i:s").'=====================', FILE_APPEND);
        if(is_array($content)){
            foreach ($content as $k => $v){
                if(is_array($v)){
                    $this->wrlog($v);
                }else{
                    file_put_contents($file, PHP_EOL . $k.'=>'.$v, FILE_APPEND);
                }
            }
        }else{
            file_put_contents($file, PHP_EOL . $content, FILE_APPEND);
        }
        fclose($doc);
    }
}
