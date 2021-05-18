<?php
foreach (glob(dirname(__DIR__) . '/raw/punish/*/item_*.html') as $htmlFile) {
    $p = pathinfo($htmlFile);
    $p['dirname'] = str_replace('/raw/', '/data/', $p['dirname']);
    $p['filename'] = substr($p['filename'], 5);
    if(!file_exists($p['dirname'])) {
        mkdir($p['dirname'], 0777, true);
    }
    $page = file_get_contents($htmlFile);
    $page = str_replace('&nbsp;', '', $page);
    $pos = strpos($page, '<div id="mainPanel">');
    $posEnd = strpos($page, '<input type="submit" name="btnExit"', $pos);
    $lines = explode('</tr>', substr($page, $pos, $posEnd - $pos));
    $punishments = [];
    foreach($lines AS $line) {
        $cols = explode('</td>', $line);
        if(count($cols) === 7) {
            foreach($cols AS $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            array_pop($cols);
            $punishments[] = $cols;
        }
    }
    file_put_contents($p['dirname'] . '/' . $p['filename'] . '.json', json_encode($punishments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
