<?php
/**
 * Модуль разработан в компании GateOn предназначен для CMS Drupal 8.1.x + Ubercart 4
 * Сайт разработчикa: www.gateon.net
 * E-mail: www@smartbyte.pro
 * Версия: 1.1
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
        $form['api_id'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('API Id'),
            '#default_value' => $this->configuration['api_id'],
            '#description' => $this->t('Настройки аккаунта, вкладка API'),
            '#required' => TRUE,
        );
        $form['api_key'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('API key'),
            '#default_value' => $this->configuration['api_key'],
            '#description' => $this->t('Настройки аккаунта, вкладка API'),
            '#required' => TRUE,
        );
        $form['api_mode'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Статус API'),
            '#options' => array(
                'TRUE' => $this->t('Включено'),
                'FALSE' => $this->t('Отключено'),
            ),
            '#default_value' => $this->configuration['api_mode']
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
        $this->configuration['api_mode'] = $form_state->getValue('api_mode');
        $this->configuration['api_id'] = $form_state->getValue('api_id');
        $this->configuration['api_key'] = $form_state->getValue('api_key');
        $this->configuration['account_Id'] = $this->getAccountApi($this->configuration);

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
      'ik_ia_u' => Url::fromRoute('uc_interkassa.notification', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_fal_u' => Url::fromRoute('uc_interkassa.complete', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_suc_u' => Url::fromRoute('uc_interkassa.complete', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_pnd_u' => Url::fromRoute('uc_interkassa.complete', ['uc_order' => $order->id()])->setAbsolute()->toString(),
      'ik_pw_no' => ''

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


        $form['#action'] = "https://sci.interkassa.com/";
        foreach ($data as $name => $value) {
      $form[$name] = array('#type' => 'hidden', '#value' => $value);
    }
    $form['actions'] = array('#type' => 'actions');

    if ($this->configuration['api_mode'] =='TRUE') {
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
    


	public function getIkPaymentSystems($ik_co_id, $ik_api_id,$ik_api_key){
    $username = $ik_api_id;
    $password = $ik_api_key;
    $remote_url = 'https://api.interkassa.com/v1/paysystem-input-payway?checkoutId=' . $ik_co_id;
        
        $businessAcc ='5d8b3cbc1ae1bd2cd78b4574';
   
            
        $ikHeaders = [];
        $ikHeaders[] = "Authorization: Basic " . base64_encode("$username:$password");
        if (!empty($businessAcc)) {
            $ikHeaders[] = "Ik-Api-Account-Id: " . $businessAcc;
        }
                
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $ikHeaders);
        $response = curl_exec($ch);
        $json_data = json_decode($response);
        
        if (empty($json_data))
            echo'<strong style="color:red;">Error!!! System response empty!</strong>';

            if ($json_data->status != 'error') {
                $payment_systems = array();
                if (!empty($json_data->data)) {
                    
                    foreach ($json_data->data as $ps => $info) {
                        $payment_system = $info->ser;
                        if (!array_key_exists($payment_system, $payment_systems)) {
                            $payment_systems[$payment_system] = array();
                            foreach ($info->name as $name) {
                                if ($name->l == 'en') {
                                    $payment_systems[$payment_system]['title'] = ucfirst($name->v);
                                }
                                $payment_systems[$payment_system]['name'][$name->l] = $name->v;
                            }
                        }
                        $payment_systems[$payment_system]['currency'][strtoupper($info->curAls)] = $info->als;
                    }
                }
                    
                return !empty($payment_systems) ? $payment_systems : '<strong style="color:red;">API connection error or system response empty!</strong>';
            } else {
                if (!empty($json_data->message))
                    echo '<strong style="color:red;">API connection error!<br>' . $json_data->message . '</strong>';
                else
                    echo '<strong style="color:red;">API connection error or system response empty!</strong>';
            }
        }


//=================
public function uc_interkassa_form_alter(&$form, $state, $form_id)
{
   
    if ($form_id == 'uc_cart_checkout_review_form') {
        if (($ik_pm_no = intval($_SESSION['cart_order'])) > 0) {
            if (empty($state['post'])) {
                $order = uc_order_load($ik_pm_no);
                if ($order->payment_method == 'interkassa') {
                    unset($form['submit']);
                    $form['#prefix'] = '<table style="display: inline;"><tr><td>';
                    $uc_form = 'uc_interkassa_form';
                    $drupal_form = drupal_get_form($uc_form, $order);
                    $form['#suffix'] = '</td><td>' . drupal_render($drupal_form) . '</td></tr></table>';
                }
            }
        }
    }

}

  public function getAccountApi($configuration) {
    $accountId = "";
    $username = $configuration['api_id'];
    $password = $configuration['api_key'];
    if ($configuration['api_mode']) {
       $tmpLocationFile = __DIR__ . '/tmpLocalStorageBusinessAcc.ini';
            $dataBusinessAcc = function_exists('file_get_contents') ? file_get_contents($tmpLocationFile) : '{}';
            $dataBusinessAcc = json_decode($dataBusinessAcc, 1);
            $businessAcc = is_string($dataBusinessAcc['businessAcc']) ? trim($dataBusinessAcc['businessAcc']) : '';
            if (empty($businessAcc) || sha1($username . $password) !== $dataBusinessAcc['hash']) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, 'https://api.interkassa.com/v1/' . 'account');
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Basic " . base64_encode("$username:$password")]);
                $response = curl_exec($curl);
                $response = json_decode($response,1);


                if (!empty($response['data'])) {
                    foreach ($response['data'] as $id => $data) {
                        if ($data['tp'] == 'b') {
                            $businessAcc = $id;
                            break;
                        }
                    }
                }

                if (function_exists('file_put_contents')) {
                    $updData = [
                        'businessAcc' => $businessAcc,
                        'hash' => sha1($username . $password)
                    ];
                    file_put_contents($tmpLocationFile, json_encode($updData, JSON_PRETTY_PRINT));
                }

                return $businessAcc;
            }

            return $businessAcc;
    }
    return $businessAcc;
  }

}
