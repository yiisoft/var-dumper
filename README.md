<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
         <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii VarDumper</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/var-dumper/v/stable.png)](https://packagist.org/packages/yiisoft/var-dumper)
[![Total Downloads](https://poser.pugx.org/yiisoft/var-dumper/downloads.png)](https://packagist.org/packages/yiisoft/var-dumper)
[![Build Status](https://github.com/yiisoft/var-dumper/workflows/build/badge.svg)](https://github.com/yiisoft/var-dumper/actions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/var-dumper/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/var-dumper/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/var-dumper/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/var-dumper/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fvar-dumper%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/var-dumper/master)
[![static analysis](https://github.com/yiisoft/var-dumper/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/var-dumper/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/var-dumper/coverage.svg)](https://shepherd.dev/github/yiisoft/var-dumper)

`VarDumper` enhances functionality of `var_dump()` and `var_export()`. It is dealing with recursive references,
may highlight syntax, export closures and output as JSON.

## Requirements

- PHP 7.4 or higher.

## Installation

The package could be installed with composer:

```shell
composer require yiisoft/var-dumper --prefer-dist
```

## General usage

### Quick debugging

In case you want to echo a string representing variable contents use the following code:

```php
\Yiisoft\VarDumper\VarDumper::dump($variable);
```

That is useful for quick debugging. By default, it goes deep `10` levels into variable and highlights syntax. You may
adjust these settings via second and third argument of the method respectively.

### Formatting debug string

To get a string representing variable contents, same as above but without `echo`:

```php
$string = \Yiisoft\VarDumper\VarDumper::create($variable)->asString(10, true);
```

`10` is maximum recursion depth and `true` is telling dumper to
highlight syntax.

### Exporting as PHP code

In order to get a valid PHP expression string that can be evaluated by PHP parser,
and the evaluation result will give back the variable value, use the following code:

```php
$string = \Yiisoft\VarDumper\VarDumper::create($variable)->export();
```

It is similar to `var_export()` but uses short array syntax, handles closures, and serializes objects.

In the above `export()` will give you nicely formatted code. You can remove formatting by passing `false` as an argument.

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## License

The Yii VarDumper Helper is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
