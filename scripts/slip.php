<?php
$rootPath = dirname(__DIR__);

$now = date('Y-m-d H:i:s');

exec("cd {$rootPath} && /usr/bin/git pull");

exec("php -q {$rootPath}/scripts/04_slip2data.php"); // convert slip html to data
exec("php -q {$rootPath}/scripts/05_summary1.php"); // calculate fee summary for using in icon label
exec("php -q {$rootPath}/scripts/03_geocoding.php"); // generate final geojson
exec("php -q {$rootPath}/scripts/01_kids_vehicle.php"); // generate final geojson

exec("cd {$rootPath} && /usr/bin/git add -A");

exec("cd {$rootPath} && /usr/bin/git commit --author 'auto commit <noreply@localhost>' -m 'auto update @ {$now}'");

exec("cd {$rootPath} && /usr/bin/git push origin master");
