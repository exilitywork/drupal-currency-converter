<?php

namespace Drupal\currency\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\currency\Utility\Currency;

/**
 * Implements table configuration form.
 */
class CurrencyConfigForm extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static($container->get('config.factory'));
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'currency_config_form';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return ['currency.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // form element: input access key of fixer.io API
        $form['access_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('fixer.io API access key'),
            '#required' => TRUE,
            '#default_value' => $this->config('currency.settings')->get('access_key'),
            '#description' => $this->t('Enter your access key for API of fixer.io and save settings to load symbols and rates'),
        ];

        // add to form 2 tabs - currency list and history
        $form['options'] = array(
            '#type' => 'vertical_tabs',
            '#default_tab' => 'edit-publication',
        );

        // load list of symbols
        $form['currency_list'] = array(
            '#type' => 'details',
            '#title' => $this->t('Currency list'),
            '#group' => 'options',
        );

        $options = [];

        $query = \Drupal::database()->select('currency_list', 'cl');
        $query->fields('cl', ['symbol', 'description', 'is_used']);
        $currencies = $query->execute()->fetchAll();

        $header = array(
            'symbol'        => 'Symbol',
            'description'   => 'Description',
        );

        $default_value = [];
    
        foreach($currencies as $index => $currency) {
            $options[$currency->symbol] = array(
                'symbol' => t($currency->symbol),
                'description' => t($currency->description),
            );
            if($currency->is_used) $default_value[$currency->symbol] = 1;
        }

        $form['currency_list']['list'] = array(
            '#type' => 'tableselect',
            '#header' => $header,
            '#options' => $options,
            '#empty' => t('Symbols not loaded'),
            '#default_value' => $default_value,
        );

        // load currency history
        $form['currency_history'] = array(
            '#type' => 'details',
            '#title' => $this->t('Currency history'),
            '#group' => 'options',
        );

        $form['currency_history']['date'] = array(
            '#type' => 'date',
            '#title' => t('Date of exchange rates'),
            '#default_value' => date('Y-m-d'),
            '#ajax' => [
                'callback' => '::getRates',
                'disable-refocus' => TRUE,
                'event' => 'change',
                'wrapper' => 'rate-table',
                'progress' => [
                    'type' => 'none',
                ],
            ]
        );

        $symbols = Currency::getActiveCurrencies();
        $base = array_key_first($symbols);
        
        $form['currency_history']['base_currency'] = [
            '#type' => 'select',
            '#title' => t('Base currency'),
            '#options' => $symbols,
            '#ajax' => [
                'callback' => '::getRates',
                'disable-refocus' => TRUE,
                'event' => 'change',
                'wrapper' => 'rate-table',
                'progress' => [
                    'type' => 'none',
                ],
            ]
        ];

        $header = array(
            'currencies'    => 'Currencies',
            'rates'         => 'Rates',
        );

        /*$default_value = [];
  
        foreach($currencies as $index => $currency) {
            $options[$currency->symbol] = array(
                'symbol' => t($currency->symbol),
                'description' => t($currency->description),
            );
            if($currency->is_used) $default_value[$currency->symbol] = 1;
        }
        $options = [];
        $options['add'] = array(
            'currencies' => 'dsfdsf',
            'rates' => 'ewew',
        );*/
        $form['currency_history']['rate'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $this->getRateRows($base, date('Y-m-d')),
            '#empty' => t('No rates'),
            '#prefix' => '<div id="rate-table">',
            '#suffix' => '</div>',
        );

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $this->config('currency.settings')
            ->set('access_key', $form_state->getValue('access_key'))
            ->save();
        
        foreach($form_state->getValue('field_tableselect') as $symbol => $isChecked) {
            $query = \Drupal::database()->update('currency_list');
            $query->fields(['is_used' => ($isChecked ? 1 : 0)]);
            $query->condition('symbol', $symbol);
            $query->execute();
        }

        Currency::loadSymbols();
        Currency::loadLatestRates();

        parent::submitForm($form, $form_state);
    }

    /**
     * Function called by AJAX request: load rates from DB
     */
    public function getRates(array &$form, FormStateInterface $form_state) {
        $base = $form_state->getValue('base_currency');
        $date = $form_state->getValue('date');
        $form['currency_history']['rate']['#rows'] = $this->getRateRows($base, $date);
        return $form['currency_history']['rate'];
    }

    /**
     * Get array of rates
     * 
     * @param string $base Currency
     * @param string $date Date of rates
     * 
     * @return array|empty
     */
    public function getRateRows($base, $date) {
        $options = [];
        $query = \Drupal::database()->select('currency_history', 'ch');
        $query->fields('ch', ['rate']);
        $query->condition('timestamp', strtotime($date), '=');
        $count = $query->countQuery()->execute()->fetchField();
        if($count) {
            $symbols = Currency::getActiveCurrencies();
            unset($symbols[$base]);
            foreach($symbols as $symbol) {
                $options[$symbol] = array(
                    'currencies'    => $base.'/'.$symbol,
                    'rates'         => Currency::getRate('EUR', $base, $symbol, $date),
                );
            }
            return $options;
        }

        return [];
    }

}
