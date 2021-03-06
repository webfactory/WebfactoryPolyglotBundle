# Changelog for WebfactoryPolyglotBundle

To get the diff for a specific change, go to https://github.com/webfactory/polyglot-bundle/commit/XXX where XXX is the change hash. To get the diff between two versions, go to https://github.com/webfactory/polyglot-bundle/compare/{oldversion}...{newversion}.

## Version 2.0.0

* Introduced method `isTranslatedInto($locale)` to the `TranslatableInterface`. The bundled implementations are enhanced accordingly, but if you have a custom implementation of TranslatableInterface, you'll need to update it. 

## Version 1.1.2

* Changed priority for the Doctrine listener to -100 to defer injection of translation objects.
  That should make it possible to mark fields as translatable that have
  their value managed by other Doctrine extensions. ([GitHub PR #6](https://github.com/webfactory/polyglot-bundle/pull/6/))

## Version 1.1.1

* Support for Symfony 3
* Dropped support for PHP < 5.5
