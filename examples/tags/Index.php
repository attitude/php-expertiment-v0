<?php

namespace Phpx\Jsx;

return implode("\n", [
  "<!doctype html>",
  Jsx::jsx('html', [
    'class' => "no-js",
    'lang' => $lang ?? 'en',
    'children' => [
      Jsx::jsx('Head', [
        'title' => $title ?? null,
      ]),
      Jsx::jsx('Body', [
        'children' => [
          $children ?? null,
        ],
      ]),
    ],
  ]),
]);
