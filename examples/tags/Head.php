<?php

namespace Phpx\Jsx;

return Jsx::jsx('head', [
  'children' => [
    Jsx::jsx('title', [
      'children' => [
        $title ?? '',
      ],
    ]),
  ],
]);
