<?php

require_once 'vendor/autoload.php';

require_once 'vendor/yandex-money/yandex-money-sdk-php/lib/api.php';
require_once "constants.php";

use \YandexMoney\API;

$app = new \Slim\Slim(array(
    "debug" => true,
    "templates.path" => "./views",
    "view" => new \Slim\Views\Twig(),
));

$app->get('/', function() use($app) { 
    $access_token = $app->request->get('token');
    return $app->render("index.html", array(
        "token" => $access_token,
        "is_result" => false
    ));
}); 

$app->post("/obtain-token/", function () use ($app) {
    $scope = $app->request->post('scope');
    $url = API::buildObtainTokenUrl(
        CLIENT_ID,
        REDIRECT_URL,
        explode(" ", $scope)
    );
    $app->redirect($url);
});

function build_relative_url($redirect_url) {
    $exploded_url = explode('/', $redirect_url);
    $relative_url_array = array_slice($exploded_url, 3);
    if($relative_url_array[count($relative_url_array) - 1] == "") {
        array_pop($relative_url_array);
    }
    return "/" . implode('/', $relative_url_array) . "/";
}

function read_sample($sample_path) {
    $full_path = sprintf("code_samples/%s.txt", $sample_path);
    $file = fopen($full_path, "r")
        or die("Unable to open file!");
    $content = fread($file, filesize($full_path));
    fclose($file);
    return $content;
}

function build_response($app, $account_info, $operation_history, $request_payment,
    $process_payment) {

    if(count($operation_history->operations) < 3) {
        $operation_history_info =  sprintf(
            "You have less then 3 records in your payment history");
    }
    else {
        $operation_history_info =  sprintf(
            "The last 3 payment titles are: %s, %s, %s",
            $operation_history->operations[0]->title,
            $operation_history->operations[1]->title,
            $operation_history->operations[2]->title
        );
    }
    if($request_payment->status == "success") {
        $request_payment_info = "Response of request-payment is successive.";
        $is_process_error = false;
    }
    else {
        $request_payment_info = "Response of request-payment is errorneous."
            . sprintf(" The error label is %s", $request_payment->error);
        $is_process_error = true;
    }
    if($is_process_error) {
        $process_payment_info = "The request-payment returns error. No operation.";
    } 
    else {
        $process_payment_info = sprintf("You send %g to %s wallet",
            $process_payment->credit_amount,
            $process_payment->payee);
    }

    return $app->render("index.html", array(
        "methods" => array(
            array(
                "info" => sprintf("You wallet balance is %s RUB",
                    $account_info->balance),
                "code" => read_sample("account_info"),
                "name" => "Account-info",
                "response" => $account_info
            ),
            array(
                "info" => $operation_history_info,
                "code" => read_sample("operation_history"),
                "name" => "Operation-history",
                "response" => $operation_history
            ),
            array(
                "info" => $request_payment_info,
                "code" => read_sample("request_payment"),
                "name" => "Request-payment",
                "response" => $request_payment
            ),
            array(
                "info" => $process_payment_info,
                "code" => read_sample("process_payment"),
                "name" => "Process-payment",
                "response" => $process_payment,
                "is_error" => $is_process_error,
                "message" => "Call process_payment method isn't possible."
                    . " See request_payment JSON for information"
            )
        ),
        "is_result" => true,
        "json_format_options" => JSON_PRETTY_PRINT
            | JSON_HEX_TAG
            | JSON_HEX_QUOT
            | JSON_HEX_AMP
            | JSON_UNESCAPED_UNICODE
    ));
}

$app->get(build_relative_url(REDIRECT_URL), function () use($app) {
    $code = $app->request->get('code');
    $access_token = API::getAccessToken(CLIENT_ID, $code,
        REDIRECT_URL, CLIENT_SECRET)->access_token;

    $api = new API($access_token);
    $account_info = $api->accountInfo();
    $operation_history = $api->operationHistory(array("records"=>3));
    $request_payment = $api->requestPayment(array(
        "pattern_id" => "p2p",
        "to" => "410011161616877",
        "amount_due" => "0.02",
        "comment" => "test payment comment from yandex-money-php",
        "message" => "test payment message from yandex-money-php",
        "label" => "testPayment",
        "test_payment" => true
    ));
    if($request_payment->status !== "success") {
        $process_payment = array();
    }
    else {
        $process_payment = $api->processPayment(array(
            "request_id" => $request_payment->request_id,
            "test_payment" => true
        ));
    }
    return build_response($app, $account_info, $operation_history,
        $request_payment, $process_payment);
});

$app->get("/debug/", function () use($app) {
    $account_info = json_decode(json_encode(array(
        "balance" => "0.01"
    )), false);
    $operation_history = json_decode(json_encode(array(
        "operations" => array(
            array(
                "title" => "foo"
            ),
            array(
                "title" => "foo1"
            ),
            array(
                "title" => "foo2"
            )
        )
    )), false);

    $request_payment = json_decode(json_encode(array(
        "status" => "success"
    )), false);
    $process_payment = json_decode(json_encode(array(
        "credit_amount" => 1.1,
        "payee" => "some person"
    )), false);
    return build_response($app, $account_info, $operation_history, $request_payment,
        $process_payment);
});
$app->run(); 

