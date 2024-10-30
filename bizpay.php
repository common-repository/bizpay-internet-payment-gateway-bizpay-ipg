<?php

/**
 * bizpay library
 * this is main biz pay class perform all the actions of the payments
 * @package BizPay API
 * @author BizPay (Pvt) Ltd
 * @copyright 2015
 * @version $Id$
 * @access public
 */
class bizpay
{ 
    /**
     * set all your biz pay settings here
     */
    public $bizpaydemohost = "https://bizpay-dev.invoice.lk";
    public $bizpaylivehost = "https://www.bizpay.lk/Payments";
    public $bizpayhost = "https://bizpay-dev.invoice.lk";
    
    /**
     * bizpay::payment_array()
     * this will return a payment array to pass it to biz pay gateway
     * @param mixed $form_array
     * @return
     */
    function payment_array($form_array, $override = null)
    {
        if (is_array($override)) {
            foreach ($override as $row => $val) {
                $this->$row = $val;
            }
        }      
        $data = array(
            "merchant" => $form_array['merchant'],
            "apikey" => $form_array['apikey'],
            "apitoken" => $form_array['apitoken'],
            "amount" => $form_array['amount'],
            "refnumber" => $form_array['refnumber'],
            "description" => $form_array['description'],
            "customer" => $form_array['customer'],
            "company" => $form_array['company'],
            "address" => $form_array['address'],
            "mobile" => $form_array['mobile'],
            "email" => $form_array['email'],
            "receipturl" => $form_array['receipturl'],
            "currency" => $form_array['currency'],
            "returnmode" => 'POST'
        );
        return $data;
    }
    
    /**
     * bizpay::pay_request()
     * this fucntion will run when user has submitted the form to proceed for payment 
     * @return void
     */
    function pay_request($request_array, $override = null)
    {        
        if (!$request_array['demomode']) {
            $bizpayhost = $bizpaylivehost;
        }
        
        $data_string = $this->payment_array($request_array, $override);
        $data_string = json_encode($data_string);
        $ch          = curl_init($this->bizpayhost . '/BizPayApi/GetToken');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length:' . strlen($data_string)
        ));
        $result     = curl_exec($ch);
        $jsonresult = json_decode($result, true);
        // var_dump($jsonresult);
        $status     = $jsonresult['status'];
        
        if ($status != "error") {
             $token       = $jsonresult['token'];
           // $message     = $jsonresult['message'];
		   // $salesnumber = $jsonresult['salesnumber'];
            header('Location:' . $this->bizpayhost . '/IPG/Pay?token=' . $token);
        } else {
            $message = $jsonresult['message'];
            echo $message;
        }
    }
    
    /**
     * bizpay::in_response()
     * this function will only in the response from the the biz pay.
     * @return void
     */
    function in_response($override = null, $payment_array)
    {
        
        if (!$payment_array['demomode']) {
            $bizpayhost = $bizpaylivehost;
        }
        
        $token    = $payment_array['token'];
        $approval = $payment_array['approval'];
        $merchant = $payment_array['merchant'];
        $apikey   = $payment_array['apikey'];
        $apitoken = $payment_array['apitoken'];
        if (is_array($override)) {
            foreach ($override as $row => $val) {
                $this->$row = $val;
            }
        }
        $data = array(
            "merchant" => $merchant,
            "apikey" => $apikey,
            "apitoken" => $apitoken,
            "salestoken" => $token,
            "approval" => $approval
        );
        
        $data_string = json_encode($data);
        $ch          = curl_init($this->bizpayhost . '/BizPayApi/ValidateSale');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ));
        $result     = curl_exec($ch);
        $jsonresult = json_decode($result, true);
        return $jsonresult;
    }
    
}
