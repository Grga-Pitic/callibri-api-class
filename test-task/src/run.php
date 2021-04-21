<?php

include_once '../vendor/autoload.php';

use App\Callibri;

const DB_HOST = 'localhost';
const DB_NAME = 'test_task';
const DB_LOGIN = 'root';
const DB_PASSWORD = 'root';

$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_LOGIN, DB_PASSWORD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$api = new Callibri(
    'https://api.callibri.ru',
    'ivan.grigorjev@lc-rus.com',
    '63qDw8TB6RAANwq24Y2u',
    $db
);

$dateFrom = new DateTime('11.04.2021');
$dateTo = new DateTime('16.04.2021');

$callsData = $api->getData($dateFrom, $dateTo);
$saveResult = $api->saveData($callsData[0]);

if ($saveResult === true) {
    echo 'Успешно сохранено!';
    die();
}

echo $saveResult;

