<?php
// Local visual fixture server. Never registered with Laravel or deployed as a route.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if($path==='/qa/check-directory.js'){header('Content-Type: application/javascript');readfile(__DIR__.'/check-directory.js');return true;}
if(preg_match('~^/preview/(drivers|driver-profile|driver-create|driver-access|incidents|incident-detail|incident-create|returns|return-detail|return-collect)\.html$~D',$path,$match)){header('Content-Type: text/html; charset=utf-8');readfile(dirname(__DIR__,2).'/storage/app/operations-visual/'.$match[1].'.html');return true;}
if($path==='/qa/check-operations.js'){header('Content-Type: application/javascript');readfile(__DIR__.'/check-operations.js');return true;}
if($path==='/qa/check-supervision.js'){header('Content-Type: application/javascript');readfile(__DIR__.'/check-supervision.js');return true;}
if($path==='/qa/check-tracking.js'){header('Content-Type: application/javascript');readfile(__DIR__.'/check-tracking.js');return true;}
if (preg_match('~^/preview/(operations|tours|create|detail|assignment|dashboard|mission|assign|deliveries|map|tracking|tracking-mission|tracking-driver)\.html$~D', $path, $match)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__,2).'/storage/app/operations-visual/'.$match[1].'.html');
    return true;
}
if (str_starts_with($path, '/css/') || str_starts_with($path, '/js/') || str_starts_with($path, '/fonts/') || str_starts_with($path, '/vendor/leaflet/')) return false;
http_response_code(404);
echo 'Visual fixture server';
