# WP Context

A single-class utility to check the current request context in WordPress sites.

---
![PHP Quality Assurance](https://github.com/inpsyde/wp-context/workflows/PHP%20Quality%20Assurance/badge.svg)
---

## How to use

This is a Composer package, not a plugin, so first it needs to be installed via Composer.

After that, assuming Composer autoload file is loaded, very early in the load process it is possible to instantiate the `WpContext` like this:

```php
$context = Inpsyde\WpContext::determine();
```

The library does not implement singleton pattern, nor caches the retrieval of the current context, so it might be a good idea to save the created instance somewhere globally accessible in your plugin/theme/package/application.

Having an instance of `WpContext`, it is possible to check the current context via its `is` method, or context-specific methods.

For example:

```php
use Inpsyde\WpContext;

$context = WpContext::determine();
if ($context->is(WpContext::AJAX, WpContext::CRON)) {
    // stuff for requests that are either AJAX or WP cron
} elseif ($context->isBackoffice()) {
    // stuff for "backoffice" requests (WP admin)
}
```

The method `WpContext::is()` is convenient to check multiple contexts, context-specific methods are probably better to check a single context.

The full list of contexts that can be checked is:

- `->is(WpContext::CORE)` / `->isCore()`
- `->is(WpContext::FRONTOFFICE)` / `->isFrontoffice()`
- `->is(WpContext::BACKOFFICE)` / `->isBackoffice()`
- `->is(WpContext::AJAX)` / `->isAjax()`
- `->is(WpContext::LOGIN)` / `->isLogin()`
- `->is(WpContext::REST)` / `->isRest()`
- `->is(WpContext::CRON)` / `->isCron()`
- `->is(WpContext::CLI)` / `->isWpCli()`
- `->is(WpContext::XML_RPC)` / `->isXmlRpc()`
- `->is(WpContext::INSTALLING)` / `->isInstalling()`
- `->is(WpContext::WP_ACTIVATE)` / `->isWpActivate()`

### About "core" and "installing" contexts

`WpContext::isCore()` checks for the constants `ABSPATH` being defined, which means that it will normally be true when all the check for other contexts is also true, but `WpContext::isInstalling()` is an exception to that (more on this below).
Another possible exception is WP CLI commands that run before WordPress is loaded.

`WpContext::isInstalling()` is true when the constant `WP_INSTALLING` is defined and true, that is when WordPress is installing or upgrading.

In this phase, `WpContext` returns `false` for all the other contexts (except for `WpContext::isWpCli()`, which will be true if the installation/update is happening via WP CLI).

For example, if a cron request is started, and WordPress for any reason sets the `WP_INSTALLING` constant during that request, `WpContext::isCron()` will be `false`, just like `WpContext::isCore()`.

The reason for this is that WordPress is likely not behaving as expected during installation.

For example a code like the following:

```php
if (Inpsyde\WpContext::determine()->isCore()) {
    return get_option('some_option');
}
```

which might look very fine, could break if `WP_INSTALLING` is true, considering in that case the options table might not be there at all. Thanks to the fact that `WpContext::isCore()` returns false when `WP_INSTALLING` is true the `get_option` call above is not executed during installation (when
it is not safe to call).

### About "installing" and "activate" contexts

The previous section states:

> `WpContext::isInstalling()` is true when the constant `WP_INSTALLING` is defined and true

but there's an exception to that.

When visiting `/wp-activate.php` the constant `WP_INSTALLING` is defined and true, however the issues that usually apply in that case (WP not fully reliable) don't apply there. In fact, when in `/wp-activate.php`, no "installations" happens, and WP is fully loaded.

This is why `/wp-activate.php` is a sort of "special case" and WP Context can determine that case via `WpContext::isWpActivate()`. When that returns true, `WpContext::isInstalling()` will return false, and `WpContext::isCore()` will return true, even if `WP_INSTALLING` is defined and true.

Please note that `/wp-activate.php` is only available for multisite installations and `WpContext::isWpActivate()` will always return false in single-site installations.



## Ok, but why?

WordPress has core functions and constants to determine the context of current request, so why an additional package?

There are multiple reasons:

- Not all contexts have a way to be determined. For example how do you determine when in a "front-office" context? And what about login screen?
- Some contexts have a dedicated constant/function, but only available late in the request flow. For example, REST requests can be checked via the `REST_REQUEST` constant, but that is only defined pretty late. `WpContext::isRest()` instead, can be used very early.
- Unit tests. Any logic that depends on PHP constants makes unit-testing hard, because it forces running tests in separate processes to be able to test different values for the same constant.
  On top of that, when running tests without WordPress being loaded it might be needed to "mock" a few WordPress functions, constants, global variables, etc. As documented below this package make tests very easy.



## Testing code that uses `WpContext`

Considering that `WpContext` uses constants and functions to determine current WordPress context it could be hard to unit-test code that make use of it, especially when WordPress is not loaded at all.

In tests, it is possible to obtain an instance of `WpContext` by calling `WpContext::new` instead of `WpContext::determine()` and then use `WpContext::force()` method to set it to the wanted context

```php
use Inpsyde\WpContext;

$context = WpContext::new()->force(WpContext::AJAX);

assert($context->isAjax());
assert($context->isCore());
```

When "forcing" a content different from `INSTALLING` or `CLI`, the context `CORE` is also set to true, not being possible to have, for example, an WordPress AJAX request outside of WordPress core.

The only context, besides `CORE`, that can be associated with other contexts is `CLI`.
However, `force` method only accepts a single context, so by using it is not possible to "simulate" a request that is, for example, both `CLI` and `CRON`.

For this scope, `WpContext` has a `withCli` method, that unlike `force` does not override current context, but only "appends" `CLI` context.

For example:

```php
use Inpsyde\WpContext;

$context = WpContext::new()->force(WpContext::CRON)->withCli();

assert($context->isCron());
assert($context->isCore());
assert($context->isWpCli());
```

Note that `$context->force(WpContext::CLI)` can still be used to "simulate" requests that are _only_ WP CLI, not even `CORE`.



## Crafted by Inpsyde

The team at [Inpsyde](https://inpsyde.com) is engineering the Web since 2006.



## License

Copyright (c) 2020 Inpsyde GmbH

This library is released under ["GPL 2.0 or later" License](LICENSE).



## Contributing

All feedback / bug reports / pull requests are welcome.

Before sending a PR make sure that `composer qa` outputs no errors.

It will run, in turn:

- [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer) checks with [Inpsyde code style](https://github.com/inpsyde/php-coding-standards)
- [Psalm](https://psalm.dev/) checks
- [PHPUnit](https://phpunit.de/) tests
