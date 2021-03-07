<?php

use Phpx\Jsx\Jsx;

set_include_path(dirname(__DIR__));
require_once '../vendor/autoload.php';

Jsx::registerPath('tags/');

echo Jsx::jsx('Index', [
  'title' => 'Hello world',
  'children' => 'Lorem ipsum dolor sit amet',
]);
