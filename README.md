# ![webfactory Logo](https://www.webfactory.de/bundles/webfactorytwiglayout/img/logo.png) WebfactoryPolyglotBundle

[![Build Status](https://scrutinizer-ci.com/g/webfactory/WebfactoryPolyglotBundle/badges/build.png?b=master)](https://scrutinizer-ci.com/g/webfactory/WebfactoryPolyglotBundle/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/webfactory/WebfactoryPolyglotBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/webfactory/WebfactoryPolyglotBundle/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/webfactory/WebfactoryPolyglotBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/webfactory/WebfactoryPolyglotBundle/?branch=master)

A bundle to simplify translations for Doctrine entities.

Its main advantages over similar bundles are:

* Transparency: Add translations to existing entities without any API changes.
* Fast: Entity translations are loaded eagerly from separate translation tables.
* Polyglot: Easy access to all available translations of an entity without additional database requests.

[We](https://www.webfactory.de/) use it to create multilingual navigation menus and links like "view this article in
German", where the linked URL has a locale specific slug.

If you're fine with the [known limitations](#known-limitations), read on!


## Installation

Just like any other Symfony bundle, and no additional configuration is required (wheeho!).

## Underlying Data Model and How It Works

The assumption is that you already have a "main" Doctrine entity class with some fields that you now need to make locale-specific.

To do so, we'll add a new _translation entity class_ that contains these fields plus a field for the locale. The main entity and the translation entity class will be connected with a `OneToMany` association. 
 
So, for one single _main_ entity instance, there are zero to many _translation entity_ instances – one for every locale that you have a translation for.
 
This approach reflects our experience that almost always the relevant content (field values) are maintained for a "primary" locale. This is the "authoritative" version of your content/data. Then, this content is translated for one or several "secondary" locales. The _translation entity class_ is concerned with holding this translated data only.

Now, this bundle sets up a Doctrine event listener (`\Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener`) to
be notified whenever a Doctrine entity is hydrated, that is, re-created as a PHP object based on database values.
  
This listener finds all fields in the entity that are marked as being locale-specific and replaces their value with
a value holder object. These value holders are instances of `\Webfactory\Bundle\PolyglotBundle\TranslatableInterface`. 
To learn more about the Value Holder pattern, see [Lazy Load in PoEAA](https://martinfowler.com/eaaCatalog/lazyLoad.html).
 
You can then use this interface's `translate()` method to obtain the field value for a locale of your choice:
The value holder will take care of returning either the original value present in your _main_ entity or finding the 
right _translation_ entity instance (for the matching locale) and take the field value 
from there, depending on whether you requested the _primary_ or one of the _additional_ locales. If no matching translation is found, the primary
locale's data will be used.

While this approach should work for any type of data, including objects, in your locale-dependent fields, it works particularly 
well for strings: The value holder features a `__toString()` method that will return the value for the currently
active locale whenever the value holder object is used in a string context.
 
Yet, it is worth noting that you're now dealing with the value holders in places where you previously had "your"
data or objects. They are *not* "almost" transparent proxies as those used by Doctrine because they do not
provide the same interface as the original values. Only for strings, the difference is sufficiently small.

The good news for Twig users is that as of Twig 1.33, `__toString()` support in Twig is good enough so that you need
 not care about the distinction of strings and translation value holders. So, Twig constructs like `{{ someObject.field }}` or
 `{% if someObject.field is not empty %}...` will work the same regardless of your `getField()` method returns a string 
 value or the translation value holder.
  
You think an example could help clearing up the confusion? Read on!
  
## Usage Example

Let's say you have an existing Doctrine entity `Document` that looks like this:

```php
<?php

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class Document
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     * @var string
     */
    private $text;

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }
}
```

And now we want to make the `text` translatable.

### Step 1) Update the Main Entity

1. For the main entity class, add the `Webfactory\Bundle\PolyglotBundle\Annotation\Locale` annotation to indicate your
   primary locale. That's the locale you have used for your fields so far.
2. Annotate all translatable fields with `Webfactory\Bundle\PolyglotBundle\Annotation\Translatable`.
3. Add the association for the upcoming translation, and annotate it's field with
   `Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection`. Also make sure it's initialized with an empty
   Doctrine collection.
4. You may want to change your type hint for the translated fields from string to `string|TranslatableInterface`.

This will lead you to something like the following, with some code skipped for brevity:

```php 
<?php

use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * ...
 * @Polyglot\Locale(primary="en_GB")
 */
class Document
{
    ...

    /**
     * @ORM\OneToMany(targetEntity="DocumentTranslation", mappedBy="entity")
     * @Polyglot\TranslationCollection
     * @var Collection
     */
    private $translations;

    /**
     * @Polyglot\Translatable
     * @var string|TranslatableInterface
     */
    private $text;

    public function __construct(...)
    {
        ...
        $this->translations = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text->translate();
    }
}
```

### Step 2) Create the Translation Entity

1. Create a class for the translation entity. As for the name, we suggest suffixing your main entity's name with
   "Translation". It has to contain all translated properties with regular Doctrine annotations
   (e.g. `@ORM\Column(type="text")`).
2. If you choose to name your back reference `entity` as we did in the example, you may want to extend
   `\Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation` to save yourself the hassle of rewriting some meta data
   and a getter. But extending this class is not necessary!
3. To implement the one-to-many relationship, the translation entity needs to reference to the original entity. 
   In our example, this is the `$entity` field.

Your code should look like this:

```php
<?php
    
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation;

/**
 * @ORM\Entity
 * @ORM\Table(
 *      uniqueConstraints = {
 *          @ORM\UniqueConstraint(columns={"entity_id", "locale"})
 *     }
 * )
 */
class DocumentTranslation extends BaseTranslation
{
    /**
     * @ORM\Column(type="text")
     * @var string
     */
    private $text;

    /**
     * @ORM\ManyToOne(targetEntity="Document", inversedBy="translations")
     * @var Document
     */
    private $entity;
}
```

### Step 3) Update your database schema

Use Doctrine to update your database schema, e.g. via the
[DoctrineMigrationsBundle](https://github.com/Doctrine/DoctrineMigrationsBundle).

### That's it!

Your entities will now be automatically loaded in the language corresponding to the current request's locale. If there
is no translation for the current locale, the primary locale is used as a fallback.

You probably noticed that we changed the `getText()` getter in our `Document` class above to call the `translate()` 
method. This uses the _value holder_ to obtain the underlying value, effectively returning the same type of data
as before the change. Clients calling this method will not notice a difference, but can only obtain the value for the
currently active locale.

Of course, you could also change the method to something along the lines of

```php
<?php 
...
class Document 
{ 
    ... 
    public function getText($locale = null)
    {
        return $this->text->translate($locale);    
    }
}
```

... which should be backwards-compatible as well, but allows client code to access the values for locales of their 
choice.

Your last option would be to leave the getter unchanged, return the value holder object and have your client code deal 
deal with it. This is not a 100% backwards-compatible solution, but as stated above, chances are you might get away with
it when changing string-typed fields only. The benefit of this approach would be that you can still choose the
locale further down the line.

**Caveat:** Note some subtle changes in case you're trying this approach:
```php
    $myDocument = ...;
    $text = $myDocument->getText();
    
    if ($text) { ... }  // Never holds because the value holder is returned (even if it contains a "" translation value)
    if ($text === 'someValue') { ... } // Strict type check prevents calling the __toString() method
```

## Known Limitations

It's not very comfortable to persist entities and their translations. One might think: it's just Doctrine, the
translation entity is the owning side of a cascade={"persist"}-association, so I'll just persist the translation. But
no - we seem to have broken Doctrine's lifecycle management here. As a workaround, you can persist both the main entity
and it's translation entities. See the tests for further details.

## Planned Features / Wish List

For now, each entity has one fixed primary locale. We have encountered cases in which some records were only available
in a language different from the primary locale. Therefore, we want to remove the primary locale annotation and store
this information in an attribute. This would allow each record to have its own primary locale.

## Credits, Copyright and License

Copyright 2012-2017 webfactory GmbH, Bonn. Code released under [the MIT license](LICENSE).

- <https://www.webfactory.de>
- <https://twitter.com/webfactory>
