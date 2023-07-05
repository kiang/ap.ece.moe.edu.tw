<?php
foreach (glob(dirname(__DIR__) . '/raw/punish/*/item_*.html') as $htmlFile) {
    $p = pathinfo($htmlFile);
    $p['dirname'] = str_replace('/raw/', '/docs/data/', $p['dirname']);
    $p['filename'] = substr($p['filename'], 5);
    if (!file_exists($p['dirname'])) {
        mkdir($p['dirname'], 0777, true);
    }
    $page = file_get_contents($htmlFile);
    $page = str_replace('&nbsp;', '', $page);
    $pos = strpos($page, '<div id="mainPanel">');
    $posEnd = strpos($page, '<input type="submit" name="btnExit"', $pos);
    $lines = explode('</tr>', substr($page, $pos, $posEnd - $pos));
    $punishments = $keyPool = [];
    $theFile = $p['dirname'] . '/' . $p['filename'] . '.json';
    if (file_exists($theFile)) {
        $punishments = json_decode(file_get_contents($theFile), true);
        foreach ($punishments as $punishment) {
            $keyPool[$punishment[1]] = true;
        }
    }
    foreach ($lines as $line) {
        $cols = explode('</td>', $line);
        if (count($cols) === 7) {
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            array_pop($cols);
            if (!isset($keyPool[$cols[1]])) {
                $punishments[] = $cols;
                $keyPool[$cols[1]] = true;
            }
        }
    }
    file_put_contents($theFile, json_encode($punishments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}