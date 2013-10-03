<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 * @package 		CodeMagnus Libraries
 * @subpackage 		Paypal Info Saver
 * @category    	Library
 * @authors 		Rommel Bulalacao | mightybulacs@gmail.com
 *					Raven Lagrimas | rjlagrimas08@gmail.com | https://github.com/ravenjohn
 * @copyright   	Copyright (c) 2013, CodeMagnus
 * @version 		Version 1.0
 * @link			https://github.com/CodeMagnus/PaypalInfoSaver
 * @license			see LICENSE file
 */

class Paypal_info_saver
{

	private static $paypal_token_endpoint	= 'https://api.sandbox.paypal.com/v1/oauth2/token';
	private static $paypal_vault_endpoint	= 'https://api.sandbox.paypal.com/v1/vault/credit-card';
	private static $paypal_pay_endpoint		= 'https://api.sandbox.paypal.com/v1/payments/payment';
	
	private $access_token;
	
	private static function determineCCType($ccNum)
	{
		/*
		* mastercard: Must have a prefix of 51 to 55, and must be 16 digits in length.
		* Visa: Must have a prefix of 4, and must be either 13 or 16 digits in length.
		* American Express: Must have a prefix of 34 or 37, and must be 15 digits in length.
		* Discover: Must have a prefix of 6011, and must be 16 digits in length.
		*/

		if (preg_match('/^5[1-5][0-9]{14}$/', $ccNum))
		{
			return 'master';
		}
		if (preg_match('/^4[0-9]{12}([0-9]{3})?$/', $ccNum))
		{
			return 'visa';
		}
		if (preg_match('/^3[47][0-9]{13}$/', $ccNum))
		{
			return 'amex';
		}
		if (preg_match('/^6011[0-9]{12}$/', $ccNum))
		{
			return 'discover';
		}

		throw new Exception('Cannot determine credit card type. [ ' . $ccNum . ' ]');
	}
	
	
	
	private function authenticate_paypal($paypal_client_token = NULL, $paypal_client_secret = NULL)
	{
		if(empty($paypal_client_token) || empty($paypal_client_secret))
		{
			throw new Exception('Authentication failed, missing client token or client secret.');
		}
	
		$ch = curl_init(self::$paypal_token_endpoint);
		curl_setopt($ch,CURLOPT_HTTPHEADER,array('Accept: application/json', 'Accept-Language: en_US'));
		curl_setopt($ch, CURLOPT_USERPWD, $paypal_client_token . ':' . $paypal_client_secret);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		$json = json_decode($response);
		if(isset($json->access_token))
		{
			$this->access_token = $json->access_token;
		}
		else
		{
			throw new Exception('Cannot retrieve paypal access token. Details : ' . json_encode($response));
		}
	}
	
	
	
	public function save_on_paypal($info, $client_token = NULL, $client_secret = NULL)
	{
		if(empty($this->access_token)){
			$this->authenticate_paypal($client_token, $client_secret);
		}
		if(!isset($info['payer_id']) || empty($info['payer_id'])){
			throw new Exception('Unable to save on paypal, payer_id is missing.');
		}
		if(!isset($info['number']) || empty($info['number'])){
			throw new Exception('Unable to save on paypal, number is missing.');
		}
		if(!isset($info['expire_month']) || empty($info['expire_month'])){
			throw new Exception('Unable to save on paypal, expire_month is missing.');
		}
		if(!isset($info['expire_year']) || empty($info['expire_year'])){
			throw new Exception('Unable to save on paypal, expire_year is missing.');
		}
		if(!isset($info['first_name']) || empty($info['first_name'])){
			throw new Exception('Unable to save on paypal, first_name is missing.');
		}
		if(!isset($info['last_name']) || empty($info['last_name'])){
			throw new Exception('Unable to save on paypal, last_name is missing.');
		}
		
		$info['type'] = self::determineCCType($info['number']);
		
		$info = json_encode($info);


		$ch = curl_init(self::$paypal_vault_endpoint);
		curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-Type: application/json', 'Authorization:Bearer ' . $this->access_token, 'Content-Length: ' . strlen($info)));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $info);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$response = curl_exec($ch);

		if (!$response) {
			curl_error ($ch);
		}
		
		curl_close($ch);

		$vault = json_decode($response);
		return $vault->id;
	}
	
	
	public function pay($payer_id, $vault_id, $amount, $currency, $client_token = NULL, $client_secret = NULL)
	{
		if(empty($this->access_token))
		{
			$this->authenticate_paypal($client_token, $client_secret);
		}
		
		$transaction = '{
			"intent": "sale",
			"payer": {
				"payment_method": "credit_card",
				"funding_instruments":[
				  {
					"credit_card_token":{
					  "credit_card_id":"'. $vault_id .'",
					  "payer_id":"'. $payer_id .'"
					}
				  }
				]
			},
			"transactions": [
				{
					"amount": {
						"total": "'. $amount .'",
						"currency": "'. $currency .'"
					}
				}
			]
		}';

		$ch = curl_init(self::$paypal_pay_endpoint);
		curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-Type: application/json', 'Authorization: Bearer ' . $this->access_token, 'Content-Length: ' . strlen($transaction)));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $transaction);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$response = curl_exec($ch);

		if (!$response) {
			curl_error ( $ch );
			curl_close($ch);
			throw new Exception('Unable to pay using vault_id, Details : ' . json_encode($response));
		}

		curl_close($ch);
		
		return json_decode($response, true);
	}
}
