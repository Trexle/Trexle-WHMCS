<?php

/**
 * Trexle WHMCS Gateway Module
 * (C) 2019 Trexle
 * Version 1, Fully tokenised, beta refunds
 * 1/8/2019
 */

function trexle_config()
{
    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value" => "Trexle"),
        "trexleapikey" => array("FriendlyName" => "Trexle Secret API Key", "Type" => "text", "Size" => "30",),
        "instructions" => array("FriendlyName" => "Payment Instructions", "Type" => "textarea", "Rows" => "5", "Description" => "Do this then do that etc...",),
        "testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this to test",),
    );
    return $configarray;
}

/** override default WHMCS credit card storage mechanism
 *
 */
function trexle_storeremote($params)
{
    if ($params['testmode'] == 'on')
        $params['url'] = "https://sandbox.trexle.com/"; //api/v1/charges";
    else
        $params['url'] = "https://core.trexle.com/";
    $params['full_url'] = $params['url'] . "api/v1/customers";
    $request = trexle_buildCustomerRequest($params);
    $result = trexle_callGateway($request, $params);

    if (isset($result['response']['token'])) {
        return array("status" => "success", "gatewayid" => $result['response']['token'], "rawdata" => $result);
    } else {
        return array("status" => "failed", "rawdata" => $results);
    }
}

function trexle_buildCustomerRequest($params)
{
    $request .= 'email=' . $params['clientdetails']['email'];
    $request .= '&card[number]=' . $params['cardnum'];
    $request .= '&card[expiry_month]=' . substr($params['cardexp'], 0, 2);
    $request .= '&card[expiry_year]=' . (substr($params['cardexp'], 2, 2) + 2000);
    $request .= '&card[cvc]=' . $params['cardcvv'];
    $request .= '&card[name]=' . $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    $request .= '&card[address_line1]=' . $params['clientdetails']['address1'];
    $request .= '&card[address_line2]=' . $params['clientdetails']['address2'];
    $request .= '&card[address_city]=' . $params['clientdetails']['city'];
    $request .= '&card[address_postcode]=' . $params['clientdetails']['postcode'];
    $request .= '&card[address_state]=' . $params['clientdetails']['state'];
    $request .= '&card[address_country]=' . $params['clientdetails']['country'];
    return $request;
}


function trexle_capture($params)
{
    if ($params['testmode'] == 'on')
        $params['url'] = "https://sandbox.trexle.com/"; //api/v1/charges";
    else
        $params['url'] = "https://core.trexle.com/";
    return trexle_capturePayment($params);
}

function trexle_capturePayment($params)
{

    $params['full_url'] = $params['url'] . "api/v1/charges";
    $params['token'] = $params['gatewayid'];

    $request = trexle_buildCaptureRequest($params);
    $raw_result = trexle_callGateway($request, $params);
    $results = trexle_processResult($raw_result);

    # Return Results
    if ($results["status"] == "success") {
        return array("status" => "success", "transid" => $results["transid"], "rawdata" => $results);
    } else {
        return array("status" => "error", "rawdata" => $results);
    }
}

function trexle_processResult($raw_result)
{
    $f_result = array();
    if ($raw_result['response']['success'] == 1) {
        $f_result['status'] = 'success';
        $f_result['transid'] = $raw_result['response']['token'];
        $f_result['response'] = print_r($raw_result['response'], true);
    } else {
        $r_result['status'] = 'declined';
        $f_result['response'] = print_r($raw_result, true);
    }
    return $f_result;
}

function trexle_buildCaptureRequest($params)
{
    $request = 'amount=' . round(trim($params['amount']) * 100);
    $request .= '&email=' . $params['clientdetails']['email'];
    $request .= "&currency=" . trim($params['currency']);
    $request .= '&description=' . 'Invoice No.' . $params['invoiceid'];
    $request .= '&ip_address=' . $_SERVER['REMOTE_ADDR'];
    $request .= '&customer_token=' . $params['token'];
    return $request;
}

function trexle_callGateway($request, $params)
{
    //build our request string
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $params['full_url']);
    curl_setopt($curl, CURLOPT_VERBOSE, 0);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
    curl_setopt($curl, CURLOPT_USERPWD, $params['trexleapikey'] . ':');
    //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,  2);
    //curl_setopt($curl, CURLOPT_REFERER, APP_BASE_URL);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    //curl_setopt($curl, CURLOPT_CAFILE, '/etc/apache/ssl.crt/getrust.ca.crt');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true); //return as array
}

function trexle_refund($params)
{

    if ($params['testmode'] == 'on')
        $params['url'] = "https://sandbox.trexle.com/"; //1/charges";
    else
        $params['url'] = "https://core.trexle.com/";

    $transid = $params['transid']; # Transaction ID of Original Payment
    $request = 'amount=' . round(trim($params['amount']) * 100);

    $params['full_url'] = $params['url'] . "api/v1/charges" . $transid . "api/v1/refunds";

    $result = trexle_callGateway($request, $params);

    # Return Results
    if (isset($result['response']['token'])) {
        return array("status" => "success", "transid" => $result['response']['token'], "rawdata" => $result);
    } else if (isset($result['error'])) {
        return array("status" => "error", "rawdata" => $result);
    } else {
        return array("status" => "declined", "rawdata" => $result);
    }

}
