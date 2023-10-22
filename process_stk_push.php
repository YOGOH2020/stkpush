<?php
session_start();

// Configuration settings (same as provided)
$config = array(
    "env" => "sandbox",
    "BusinessShortCode" => "174379",
    "key" => "WPlwOAd5Ipw7lBE3TwDrLSDak2xPmGdH",
    "secret" => "3Qp7L5HpslxGxYbY",
    "username" => "Trial",
    "TransactionType" => "CustomerPayBillOnline",
    "passkey" => "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919",
    "CallBackURL" => "https://mydomain.com/path",
    "AccountReference" => "TrialLTD",
    "TransactionDesc" => "Payment of Y",
);

if (isset($_POST['phone_number'], $_POST['amount'])) {
    // Extract data from the form
    $phone = $_POST['phone_number'];
    $amount = $_POST['amount'];
    
    // Normalize the phone number
    $phone = (substr($phone, 0, 1) == "+") ? str_replace("+", "", $phone) : $phone;
    $phone = (substr($phone, 0, 1) == "0") ? preg_replace("/^0/", "254", $phone) : $phone;
    $phone = (substr($phone, 0, 1) == "7") ? "254{$phone}" : $phone;

    // Generate an access token
    $access_token = ($config['env'] == "live") ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
    $credentials = base64_encode($config['key'] . ':' . $config['secret']);

    $ch = curl_init($access_token);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response);
    $token = isset($result->{'access_token'}) ? $result->{'access_token'} : "N/A";

    // Generate timestamp and password
    $timestamp = date("YmdHis");
    $password = base64_encode($config['BusinessShortCode'] . "" . $config['passkey'] . "" . $timestamp);

    // Construct data for the STK Push
    $curl_post_data = array(
        "BusinessShortCode" => $config['BusinessShortCode'],
        "Password" => $password,
        "Timestamp" => $timestamp,
        "TransactionType" => $config['TransactionType'],
        "Amount" => $amount,
        "PartyA" => $phone,
        "PartyB" => $config['BusinessShortCode'],
        "PhoneNumber" => $phone,
        "CallBackURL" => $config['CallBackURL'],
        "AccountReference" => $config['AccountReference'],
        "TransactionDesc" => $config['TransactionDesc'],
    );

    $data_string = json_encode($curl_post_data);

    // Determine the API endpoint based on the environment
    $endpoint = ($config['env'] == "live") ? "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest" : "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

    // Send the STK Push request
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode(json_encode(json_decode($response)), true);

    if ($result['ResponseCode'] === "0") {
        // STK Push request successful
        // You can handle success here, e.g., store transaction details in your database
        echo "STK Push initiated successfully!";
    } else {
        // Handle errors, e.g., display the error message to the user
        $error_message = $result['errorMessage'];
        echo "STK Push failed. Error: $error_message";
    }
}
?>
