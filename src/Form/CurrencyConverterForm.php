<?php

namespace Drupal\currency\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\currency\Utility\Currency;

/**
 * Implements table form.
 */
class CurrencyConverterForm extends FormBase {

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
        return 'currency_converter_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // get active currencies from DB
        $symbols = Currency::getActiveCurrencies();
        // get first currency from array of currencies
        $base = array_key_first($symbols);
        
        // form element: select base currency 
        $form['base_currency'] = [
            '#type' => 'select',
            '#options' => $symbols,
            '#prefix' => '<div class="currency-row currency-label w-auto">I have</div><div class="currency-row">',
            '#suffix' => '</div>',
            '#ajax' => [
                'callback' => '::calculate',
                'disable-refocus' => TRUE,
                'event' => 'change',
                'wrapper' => 'edit-quote',
                'progress' => [
                    'type' => 'none',
                ],
            ]
        ];

        // form element: input amount of base currency 
        $form['base_amount'] = [
            '#type' => 'textfield',
            '#prefix' => '<div class="currency-row">',
            '#suffix' => '</div><div class="clearfix"></div>',
            '#default_value' => 1,
            '#ajax' => [
                'callback' => '::calculate',
                'disable-refocus' => TRUE,
                'event' => 'change',
                'wrapper' => 'edit-quote',
                'progress' => [
                    'type' => 'none',
                ],
            ]
        ];

        // form element: select quote currency
        $form['quote_currency'] = [
            '#type' => 'select',
            '#options' => $symbols,
            '#prefix' => '<div class="currency-row currency-label w-auto">I want</div><div class="currency-row">',
            '#suffix' => '</div>',
            '#ajax' => [
                'callback' => '::calculate',
                'disable-refocus' => TRUE,
                'event' => 'change',
                'wrapper' => 'edit-quote',
                'progress' => [
                    'type' => 'none',
                ],
            ]
        ];

        // form element: output amount of quote currency
        $form['quote_amount'] = [
            '#type' => 'textfield',
            '#prefix' => '<div  id="edit-quote" class="currency-row">',
            '#suffix' => '</div><div class="clearfix"></div>',
            '#default_value' => 1,
            '#disabled' => TRUE,
        ];        

        // add css
        $form['#attached']['library'][] = 'currency/converter_form';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
    }

    /**
     * Function called by AJAX request; convert currency
     */
    public function calculate(array &$form, FormStateInterface $form_state) {
        $base = $form_state->getValue('base_currency');
        $quote = $form_state->getValue('quote_currency');
        $baseAmount = $form_state->getValue('base_amount');
        $form['quote_amount']['#value'] = $baseAmount * Currency::getRate('EUR', $base, $quote, date('Y-m-d'));
        return $form['quote_amount'];
    }

}
