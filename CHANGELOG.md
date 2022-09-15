# Yii VarDumper


## 1.2.1 under development

- Enh #64: Add `asJson()` method to `\Yiisoft\VarDumper\VarDumper` (@dood-)
- Enh #68: Fix json serialization (@xepozz)

## 1.2.0 October 24, 2021

- Enh #59: Add `\Yiisoft\VarDumper\VarDumper::withOffset()` to adjust generated code nesting offset (@WinterSilence)
- Enh #60: Add support for `yiisoft/arrays` version `^2.0` (@vjik)

## 1.1.0 April 08, 2021

- Enh #54: Add support for exporting objects with closures (@yiiliveext)

## 1.0.5 March 17, 2021

- Bug #53: Fix `\Yiisoft\VarDumper\ClosureExporter::export()` method fail when exporting a static method call (@devanych)

## 1.0.4 March 15, 2021

- Bug #51: Add a line break in the `d()` and `dd()` functions for highlight mode (@devanych)

## 1.0.3 February 16, 2021

- Bug #50: Fix the issue of exporting closures with use long namespace under PHP 7.4 (@devanych)

## 1.0.2 February 15, 2021

- Bug #49: Fix the issue of exporting closures when using PHP 8 (@devanych)

## 1.0.1 February 11, 2021

- Bug #48: Remove the unused `objects` property from the `\Yiisoft\VarDumper\VarDumper` class. (@devanych)

## 1.0.0 February 10, 2021

Initial release.
