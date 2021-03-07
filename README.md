# PHPX — JSX for PHP8

**⚠️ Experimental project**

Lets enhance the PHP syntaxt with JSX. Core principles:

1. keep most of the PHP8 intact
1. use Javascript logic operators
1. Write less, do more

## Features

Feature             | Usage                                               | Notes
--------------------|-----------------------------------------------------|------
strings             | `'Lorem'."ipsum"`, `'dolor "sit" amet'`             | As in PHP. See: [String operators in PHP]
variables           | `$foo`                                              | As in PHP
objects             | `$foo->bar->baz`                                    | Access to undefined properties is always null-safe without using `?->`
assignments         | `$foo = $bar + 4;`                                  | As in PHP
ternary operators   | `$foo ? bar : $baz ? $one : $two`                   | As in Javascript due [nested ternary operators without explicit parentheses]
`??`                | `$foo = $bar ?? 'default'`                          | See: [nullish coalescing operator]
callables           | `trim`, `array_map`, `Custom::method`,...           | All defined and callable methods as in PHP
expressions         | `{ $foo + $bar / $baz }`                              | See: [Embeding expressions in JSX]
JSX                 | `<div class={$className}>{$children}</div>`         | See: [JSX]
fragments           | `<>` and `</>`                                      | See: [JSX Fragments]
void emelents       | `<img>`, `<br>`, `<meta>`, `<link>`,...             | Elements that need no closing tag
text nodes          | `<p>mixed text with <b>nested bold</b><p>`          | Text node work, unlike in JSX
Logical `&&`        | `true && 0 && 1 === 0` returns `0`                  | As in JavaScript, [expression returns the first falsy value or the final truthy value][Short-circuit evaluation of AND]
Logical `\|\|`      | `1 \|\| true \|\| 0 === 1` returns `1`              | As in JavaScript, [expression returns the first truthy value or the last falsy value][Short-circuit evaluation of OR]
mapping             | `array_map(fn($bar) => <div>{$bar->baz}</div>, $foo`| See [arrow functions]

[JSX Fragments]: https://reactjs.org/docs/fragments.html
[Embeding expressions in JSX]: https://reactjs.org/docs/introducing-jsx.html#embedding-expressions-in-jsx
[JSX]: https://reactjs.org/docs/introducing-jsx.html
[Short-circuit evaluation of AND]: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Logical_AND#short-circuit_evaluation
[Short-circuit evaluation of OR]: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Logical_OR#short-circuit_evaluation
[String operators in PHP]: https://www.php.net/manual/en/language.operators.string.php
[arrow functions]: https://www.php.net/manual/en/functions.arrow.php
[nullish coalescing operator]: https://wiki.php.net/rfc/isset_ternary
[nested ternary operators without explicit parentheses]: https://www.php.net/manual/en/migration74.deprecated.php

*Example:*

```js
true && 0 && 1 === 0  // returns 0
1 || true || 0 === 1  // returns 1
true && 'true' || 0   // returns 'true'
```

---

## Writing `PHPX`

PHPX supports *full* set of PHP8 language features. Only JSX tags & locical AND and OR
from javascript are transpiled into PHP.

### Let's compare JSX to PHPX to generated PHP:

**JSX:**

```jsx
return <h1>{children ?? ''}</h1>
```

**PHPX:**

```jsx
return <h1>{$children ?? ''}</h1>;
// Note the trailing `;` --------^
```

**PHP:**

```php
<?php

namespace Phpx\Jsx;

return Jsx::jsx('h1', [
  'children' => [
    $children ?? '',
  ],
]);
```

---
## Usage
### 1. Update `composer.json` of your project:

```json
{
    ...,
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/attitude/phpx"
        }
    ],
    "scripts": {
        "watch": "php vendor/attitude/phpx/src/watch.php"
    },
    "require": {
        "attitude/phpx": "dev-main"
    }
}
```

### 2. Install dependencies

```shell
$ composer install
```

### 3. Start process to watch files while developing:

```shell
$ composer run watch
```

### 4. Create any `**/*.tag` file within your project

- Use PHP style `$variable` for variable names
- Transpiled PHP template are generated right next to it

---
## PHPX Example

### 1. Create `.tag` files:

`Index.tag`:

```jsx
return <>
  <!doctype html>
  <html class="no-js" lang={$lang ?? 'en'}>
    <Head title={$title} />
    <Body>
      {$children}
    </Body>
  </html>
</>;
```

`Head.tag`:

```html
<head>
  <title>{$title ?? ''}</title>
</head>
```

`Body.tag`:

```html
<body>
  {$children ?? ''}
</body>
```

### 2. Register tags

```php
<?php

require 'vendor/autoload.php';

use Phpx\Jsx\Jsx;

Jsx::register('your/path/to/Index.php', /*optional*/ 'Index');
Jsx::register('your/path/to/Head.php');
Jsx::register('your/path/to/Body.php');
// ... other tags

echo Jsx::jsx('Index', [
  'title' => 'Hello world',
  'children' => 'Lorem ipsum dolor sit amet',
]);
```

### 3. Use watche script to transpile:

```shell
$ composer run watch
```

### 4. Output

```html
<!doctype html>
<html class="no-js" lang="en">
  <head><title>Hello world</title></head>
  <body>Lorem ipsum dolor sit amet</body>
</html>
```

---

## Examples

There are example tags located in the [examples subfolder](./examples).

1. Clone this repository
2. Run `$ composer install`
3. Run `$ composer watch`
4. Run `$ composer serve`
5. Open http://localhost:8888/

Edit the tags and see the results.

---

*Enjoy!*

Created by [martin_adamko](https://twitter.com/martin_adamko)
