<?php
/**
 * Модуль разработан в компании GateOn предназначен для CMS Drupal 8.2.x + Ubercart 4
 * Сайт разработчикa: www.gateon.net
 * E-mail: www@smartbyte.pro
 * Версия: 1.2
 */
namespace Drupal\uc_interkassa\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;

/**
 * Defines the Interkassa payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "interksassa",
 *   name = @Translation("Interksassa"),
 *   redirect = "\Drupal\uc_interkassa\Form\InterkassaForm",
 * )
 */
class Interkassa extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface
{

    /**
     * {@inheritdoc}
     */
    public function getDisplayLabel($label)
    {
        $build['#attached']['library'][] = 'uc_interkassa/interkassa.styles';
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
    public function defaultConfiguration()
    {
        return [
            'check' => FALSE,
            'checkout_type' => 'dynamic',
            'notification_url' => '',
            'sid' => '',
            'secret_key' => '',
            'test_key' => ''
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['sid'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Interkassa ID'),
            '#description' => $this->t('ID of your Interkassa'),
            '#default_value' => $this->configuration['sid'],
            '#size' => 40,
        );
        $form['secret_key'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Секретный ключ'),
            '#description' => $this->t('Взять с настроек кассы "Безопасность" -> "Секретный ключ" '),
            '#default_value' => $this->configuration['secret_key'],
            '#size' => 40,
        );
        $form['test_key'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Тестовый ключ'),
            '#description' => $this->t('Взять с настроек кассы "Безопасность" -> "Тестовый ключ" '),
            '#default_value' => $this->configuration['test_key'],
            '#size' => 40,
        );

        $form['checkout_type'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Checkout type'),
            '#options' => array(
                'dynamic' => $this->t('Dynamic checkout (user is redirected to Interkassa)'),
                'direct' => $this->t('Direct checkout (payment page opens in iframe popup)'),
            ),
            '#default_value' => $this->configuration['checkout_type'],
        );
        $form['notification_url'] = array(
            '#type' => 'url',
            '#title' => $this->t('URL Уведомления'),
            '#description' => $this->t('Pass this URL to the <a href=":help_url">instant notification settings</a> parameter in your Interkassa account. This way, any refunds or failed fraud reviews will automatically cancel the Ubercart order.', [':help_url' => Url::fromUri('https://www.interkassa.com/documentation-sci/')->toString()]),
            '#default_value' => Url::fromRoute('uc_interkassa.notification', [], ['absolute' => TRUE])->toString(),
            '#attributes' => array('readonly' => 'readonly'),
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $this->configuration['notification_url'] = $form_state->getValue('notification_url');
        $this->configuration['sid'] = $form_state->getValue('sid');
        $this->configuration['secret_key'] = $form_state->getValue('secret_key');
        $this->configuration['test_key'] = $form_state->getValue('test_key');
    }

    /**
     * {@inheritdoc}
     */
    public function cartDetails(OrderInterface $order, array $form, FormStateInterface $form_state)
    {
        $build = array();
        $session = \Drupal::service('session');
        if ($this->configuration['check']) {
            $build['pay_method'] = array(
                '#type' => 'select',
                '#title' => $this->t('Select your payment type:'),
                '#default_value' => $session->get('pay_method') == 'CK' ? 'CK' : 'CC',
                '#options' => array(
                    'CC' => $this->t('Credit card'),
                    'CK' => $this->t('Online check'),
                ),
            );
            $session->remove('pay_method');
        }

        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state)
    {
        $session = \Drupal::service('session');
        if (NULL != $form_state->getValue(['panes', 'payment', 'details', 'pay_method'])) {
            $session->set('pay_method', $form_state->getValue(['panes', 'payment', 'details', 'pay_method']));
        }
        return TRUE;
    }

    /**
     * {@inheritdoc}
     */
    public function cartReviewTitle()
    {
        if ($this->configuration['check']) {
            return $this->t('Credit card/eCheck');
        } else {
            return $this->t('Credit card');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL)
    {
        $address = $order->getAddress('billing');
        if ($address->country) {
            $country = \Drupal::service('country_manager')->getCountry($address->country)->getAlpha3();
        } else {
            $country = '';
        }
        
        $data = array(
            'ik_co_id' => $this->configuration['sid'],
            'ik_pm_no' => $order->id(),
            'ik_desc' => '#' . $order->id(),
            'ik_am' => uc_currency_format($order->getTotal(), FALSE, FALSE, '.'),
            'ik_cur' => $order->getCurrency(),
            'ik_ia_u' => Url::fromRoute('uc_interkassa.notification', ['cart_id' => \Drupal::service('uc_cart.manager')->get()->getId()], ['absolute' => TRUE])->toString(),
            'ik_fal_u' => Url::fromRoute('uc_interkassa.complete', ['cart_id' => \Drupal::service('uc_cart.manager')->get()->getId()], ['absolute' => TRUE])->toString(),
            'ik_suc_u' => Url::fromRoute('uc_interkassa.complete', ['cart_id' => \Drupal::service('uc_cart.manager')->get()->getId()], ['absolute' => TRUE])->toString(),
            'ik_pnd_u' => Url::fromRoute('uc_interkassa.complete', ['cart_id' => \Drupal::service('uc_cart.manager')->get()->getId()], ['absolute' => TRUE])->toString()

        );

        $dataSet = $data;
        ksort($dataSet, SORT_STRING);
        array_push($dataSet, $this->configuration['secret_key']);
        $signString = implode(':', $dataSet);
        $sign = base64_encode(md5($signString, true));

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

        if ('direct' == $this->configuration['checkout_type']) {
            $form['#attached']['library'][] = 'uc_interkassa/interkasssa.direct';
        }

        $form['#action'] = "https://sci.interkassa.com/";

        foreach ($data as $name => $value) {
            $form[$name] = array('#type' => 'hidden', '#value' => $value);
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Submit order'),
        );

        return $form;
    }

}
