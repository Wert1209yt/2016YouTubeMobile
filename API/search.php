<?php
$query = trim(file_get_contents('http://localhost:3000/searchquery'));
$url = 'https://www.youtube.com/youtubei/v1/search?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
$data = [
    'context' => [
        'client' => [
            'hl' => 'ru',
            'gl' => 'RU',
            'clientName' => 'WEB',
            'clientVersion' => '2.20241011.00.00'
        ]
    ],
    'query' => $query
];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>
