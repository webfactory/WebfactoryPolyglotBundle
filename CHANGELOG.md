# Changelog for WebfactoryPolyglotBundle

To get the diff for a specific change, go to https://github.com/webfactory/polyglot-bundle/commit/XXX where XXX is the change hash. To get the diff between two versions, go to https://github.com/webfactory/polyglot-bundle/compare/{oldversion}...{newversion}.

## Version 4.0.0

* Support for annotation-based configuration of translation properties has been removed. Switch to attribute-based configuration, which has been added in 3.1.0.
* Classes from the `Webfactory\Bundle\PolyglotBundle\Attribute` namespace are now `final`.

## Version 3.1.0

* The annotations `\Webfactory\Bundle\PolyglotBundle\Annotation\Locale`, `\Webfactory\Bundle\PolyglotBundle\Annotation\Translatable` and `\Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection` have been deprecated. Replace them with the corresponding PHP attributes from the `\Webfactory\Bundle\PolyglotBundle\Attribute` namespace.
* Using annotations to configure entity classes for this bundle has been deprecated. Switch to PHP 8 attributes.
* Attribute classes will be made `final` in the next major release.

## Version 3.0.0

* Dropped support for PHP versions below 8.1, and for Symfony versions before 5.4.
* Classes internal to this bundle are now marked as `final`. Static analysis tools should be able to pick up `@final` announcements used as of v2.5.0.

## Version 2.0.0

* Introduced method `isTranslatedInto($locale)` to the `TranslatableInterface`. The bundled implementations are enhanced accordingly, but if you have a custom implementation of TranslatableInterface, you'll need to update it. 

## Version 1.1.2

* Changed priority for the Doctrine listener to -100 to defer injection of translation objects.
  That should make it possible to mark fields as translatable that have
  their value managed by other Doctrine extensions. ([GitHub PR #6](https://github.com/webfactory/polyglot-bundle/pull/6/))

## Version 1.1.1

* Support for Symfony 3
* Dropped support for PHP < 5.5
