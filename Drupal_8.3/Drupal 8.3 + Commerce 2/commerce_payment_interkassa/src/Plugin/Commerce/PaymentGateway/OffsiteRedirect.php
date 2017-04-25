<?php

namespace Drupal\commerce_payment_ik\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderStorage;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "ik_offsite_redirect",
 *   label = "Interkassa (Off-site redirect)",
 *   display_label = "Interkassa",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_payment_ik\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 * )
 */
class OffsiteRedirect extends OffsitePaymentGatewayBase
{


	public function defaultConfiguration()
	{
		return [
			'ik_co_id' => '',
			'ik_s_key' => '',
			'ik_t_key' => '',
			'ik_api' => false,
            'ik_api_id' => '',
            'ik_api_key' => '',
			'ik_test' => false
		] + parent::defaultConfiguration();
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildConfigurationForm(array $form, FormStateInterface $form_state)
	{
		$form = parent::buildConfigurationForm($form, $form_state);
		$form['ik_co_id'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Идентификатор кассы'),
			'#default_value' => $this->configuration['ik_co_id']
		];
		$form['ik_s_key'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Секретный ключ'),
			'#default_value' => $this->configuration['ik_s_key']
		];
		$form['ik_t_key'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Тестовый ключ'),
			'#default_value' => $this->configuration['ik_t_key']
		];
		$form['ik_test'] = [
			'#type' => 'checkbox',
			'#title' => $this->t('Тестовый режим'),
			'#default_value' => $this->configuration['ik_test']
		];

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
	{
		parent::submitConfigurationForm($form, $form_state);
		if (!$form_state->getErrors())
		{
			$values = $form_state->getValue($form['#parents']);
			$this->configuration['ik_co_id'] = $values['ik_co_id'];
			$this->configuration['ik_s_key'] = $values['ik_s_key'];
			$this->configuration['ik_t_key'] = $values['ik_t_key'];
			$this->configuration['ik_test'] = $values['ik_test'];
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
	public function onNotify(Request $request)
	{
		try
		{
            $ik_co_id = $this->configuration['ik_co_id'];

            if ($request && $this->checkIP() && $ik_co_id == $request->get('ik_co_id')) {

                    if ( $request->get('ik_pw_via') && $request->get('ik_pw_via') == 'test_interkassa_test_xts') {
                        $secret_key = $this->configuration['ik_t_key'];
                    } else {
                        $secret_key = $this->configuration['ik_s_key'];
                    }

                    $request = $_POST;

                    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
                    $order = $order_storage->load($request['ik_pm_no']);
                    if (is_null($order))
                    {
                        throw new Exception('Order not found');
                    }
                    // Сравниваем суммы (принятую и записанную в ORDER'е)
                    $amount = number_format($order->getTotalPrice()->getNumber(), 2, '.', '');
                    if ($request['ik_am'] !== $amount)
                    {
                        throw new Exception('Payment amount mismatch');
                    }

                    $request_sign = $request['ik_sign'];
                    unset($request['ik_sign']);

                    //удаляем все поле которые не принимают участия в формировании цифровой подписи
                    foreach ($request as $key => $value) {
                        if (!preg_match('/ik_/', $key)) continue;
                        $request[$key] = $value;
                    }

                    //формируем цифровую подпись
                    ksort($request, SORT_STRING);
                    array_push($request, $secret_key);
                    $str = implode(':', $request);
                    $sign = base64_encode(md5($str, true));

                    if ($request_sign == $sign) {

                        // Меняем статус ORDER'а и сохраняем его
                        $order->set('state','completed');
                        $order->save();
                        // Создаём платёж и сохраняем его
                        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                        $payment = $payment_storage->create([
                            'amount' => $order->getTotalPrice(),
                            'payment_gateway' => $this->entityId,
                            'order_id' => $order->id(),
                            'test' => $this->getMode() == 'test',
                            'remote_id' => $request['ik_pm_no'],
                            'remote_state' => 'PAYED',
                            'authorized' => REQUEST_TIME,
                        ]);
                        $payment->save();
                        // Если не произошло исключений, выдаём "SUCCESS"
                        echo 'OK';
                    } else {
                        throw new Exception('Payment signature mismatch');
                    }


            }else{
                throw new Exception('Request parameters is not correct');
            }

		}
		catch (Exception $e)
		{
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
	public function onReturn(OrderInterface $order, Request $request)
	{
		drupal_set_message('Payment was processed');
	}

    public function checkIP()
    {
        $ip_stack = array(
            'ip_begin' => '151.80.190.97',
            'ip_end' => '151.80.190.104'
        );
        if (ip2long($_SERVER['REMOTE_ADDR'] < ip2long($ip_stack['ip_begin']) || ip2long($_SERVER['REMOTE_ADDR']) > ip2long($ip_stack['ip_end']))) {
            throw new Exception('Someone trying to cheat us');
        }
        return true;
    }
}
