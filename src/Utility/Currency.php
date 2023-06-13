<?php

namespace Drupal\currency\Utility;

/**
 * Provides functions for module
 *
 * @ingroup utility
 */
class Currency {

    /**
     * Load symbols from fixer.io
     *
     */
    public static function loadSymbols() {
        // curl request to fixer.io API
        $config = \Drupal::config('currency.settings');
        $access_key = $config->get('access_key');
        $endpoint = 'symbols';
        $ch = curl_init('http://data.fixer.io/api/'.$endpoint.'?access_key='.$access_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);
        $symbols = isset(json_decode($json, true)['symbols']) ? json_decode($json, true)['symbols'] : [];

        // save to DB loaded data
        foreach($symbols as $symbol => $description) {
            $query = \Drupal::database()->upsert('currency_list');
            $query->fields(['symbol', 'description']);
            $query->values([$symbol, $description]);
            $query->key('symbol');
            $query->execute();
        }
    }

    /**
     * Load latest rates from fixer.io
     *
     */
    public static function loadLatestRates() {
        // curl request to fixer.io API
        $config = \Drupal::config('currency.settings');
        $access_key = $config->get('access_key');
        $endpoint = 'latest';
        $ch = curl_init('http://data.fixer.io/api/'.$endpoint.'?access_key='.$access_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);
        $exchangeRates = json_decode($json, true);

        // check stored rates on current date
        $query = \Drupal::database()->select('currency_history', 'ch');
        $query->fields('ch', ['rate']);
        $query->condition('timestamp', strtotime(date('Ymd')), '=');
        $count = $query->countQuery()->execute()->fetchField();

        // if curl request is successfull and no rates in DB on current date, save latest rates in DB
        if(isset($exchangeRates['rates']) && !$count) {
            $query = \Drupal::database()->insert('currency_history');
            $query->fields(['base', 'quote', 'rate', 'timestamp']);
            foreach($exchangeRates['rates'] as $symbol => $rate) {
                $query->values([$exchangeRates['base'], $symbol, $rate, strtotime(date("Y-m-d"))]);
            }
            $query->execute();
        }

        return $exchangeRates;
    }

    /**
     * Calculate and return rate of any stored currencies on some date
     *
     * @param string $base Base currency 
     * @param string $quote1 First quote currency 
     * @param string $quote2 Second quote currency (default: empty)
     * @param string $date Date of exchange rate (default: empty)
     * 
     * @return float
     */
    public static function getRate($base, $quote1,  $quote2 = '', $date = '') {
        if(!$date) $date = date('Y-m-d');
        $query = \Drupal::database()->select('currency_history', 'ch');
        $query->fields('ch', ['rate']);
        $query->condition('base', $base, '=');
        $query->condition('quote', $quote1, '=');
        $query->condition('timestamp', strtotime($date), '=');
        $rate = $query->execute()->fetchAssoc()['rate'];
        if($quote2) {
            $rate = number_format(1 / $rate * self::getRate($base, $quote2, '', $date), 8);
        }
        return $rate;
    }

    /**
     * Return array of active currencies
     *
     * @return array
     */
    public static function getActiveCurrencies() {
        $symbols = [];
        $query = \Drupal::database()->select('currency_list', 'ch');
        $query->fields('ch', ['id', 'symbol']);
        $query->condition('is_used', 1, '=');
        $result = $query->execute()->fetchAll();
        foreach($result as $symbol) {
            $symbols[$symbol->symbol] = $symbol->symbol;
        }
        return $symbols;
    }

    /**
     * Convert any amount from one currency to another
     *
     * @param float $amount Amount of converted currency 
     * @param string $base Base currency 
     * @param string $quote Quote currency
     * 
     * @return float
     */
    public static function convert($amount, $base, $quote) {
        return $amount * self::getRate('EUR', $base, $quote);
    }

}
