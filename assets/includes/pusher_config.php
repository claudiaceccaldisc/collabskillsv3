<?php
// includes/pusher_config.php
require __DIR__ . '/../../vendor/autoload.php';


use Pusher\Pusher;

$options = [
    'cluster' => 'eu',  
    'useTLS'  => true,
    'curl_options' => [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]
    ]
    
;

$app_id  = '1959216';
$key     = '7c78e787d24818138f9d';
$secret  = '3c5008538aa5073600cd';

$pusher = new Pusher($key, $secret, $app_id, $options);



//'curl_options' => [
        //    CURLOPT_SSL_VERIFYPEER => false,
  //  ]