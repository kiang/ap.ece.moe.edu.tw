<?php
$posEnd = 0;
foreach(glob(dirname(__DIR__) . '/raw/*.html') AS $htmlFile) {
    $page = file_get_contents($htmlFile);
    $posEnd = 0;
    $pos = strpos($page, '<h4><span id="GridView', $posEnd);
    while(false !== $pos) {
        $nextPos = strpos($page, '<h4><span id="GridView', $pos + 1);
        if(false === $nextPos) {
            $nextPos = strlen($page);
        }
        echo substr($page, $pos, $nextPos - $pos);
        exit();
    }
}