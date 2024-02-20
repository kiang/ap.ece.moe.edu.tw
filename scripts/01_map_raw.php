<?php
$areas = json_decode(file_get_contents(__DIR__ . '/areas.json'), true);
$basePath = dirname(__DIR__) . '/raw/map';
if (!file_exists($basePath)) {
  mkdir($basePath, 0777);
}
foreach ($areas as $city => $data1) {
  foreach ($data1 as $area => $code) {
    file_put_contents($basePath . '/' . $code . '.json', file_get_contents('https://ap.ece.moe.edu.tw/webecems/map/getspot.ashx?ct=&ar=' . $code . '&ad=&t1=&t2=&t3=&sp=&sn=&ne=&la=&ln='));
  }
}
