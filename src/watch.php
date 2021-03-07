<?php

require_once 'vendor/autoload.php';

$dir = getcwd();
echo "Started watching `{$dir}`\n";

while (true) {
    Phpx\Jsx\Walker::walk($dir);
    sleep(1);
}