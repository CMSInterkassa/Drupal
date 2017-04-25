<?php

namespace Drupal\commerce_payment_ik\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class PaymentOffsiteForm extends BasePaymentOffsiteForm
{
	/**
	 * {@inheritdoc}
	 */
	public function buildConfigurationForm(array $form, FormStateInterface $form_state)
	{
        global $base_url;
		$form = parent::buildConfigurationForm($form, $form_state);
		/** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
		$payment = $this->entity;
		/** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
		$payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

		$configuration = $payment_gateway_plugin->getConfiguration();
		$method = 'post';
		$action = 'https://sci.interkassa.com/';

        $data = array(
            'ik_co_id' => $configuration['ik_co_id'],
            'ik_am' => number_format($payment->getAmount()->getNumber(), 2, '.', ''),
            'ik_pm_no' => $payment->getOrderId(),
            'ik_desc' => 'Payment for order #: '. $payment->getOrderId(),
            'ik_cur' => $payment->getAmount()->getCurrencyCode() == 'RUR'? 'RUB' : $payment->getAmount()->getCurrencyCode(),
            'ik_suc_u' => $form['#return_url'],
            'ik_fal_u' => $form['#cancel_url'],
            'ik_pnd_u' => $form['#return_url'],
            'ik_ia_u' => $base_url.'/payment/notify/interkassa'
        );
        //Если включен тестовый режим то сразу перенапрвляем на Интеркассу используя тестовыую платежную систему
        if($configuration['ik_test']) $data['ik_pw_via'] = 'test_interkassa_test_xts';

        //Формируем подпись
        ksort($data, SORT_STRING);
        array_push($data, $configuration['ik_s_key']);
        $str = implode(':', $data);
        $sign = base64_encode(md5($str, true));


        $data['ik_sign'] = $sign;

		return $this->buildRedirectForm($form, $form_state, $action, $data, $method);
	}

}
