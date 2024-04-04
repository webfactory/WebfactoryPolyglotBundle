# ![webfactory Logo](https://www.webfactory.de/bundles/webfactorytwiglayout/img/logo.png) WebfactoryPolyglotBundle

![Tests](https://github.com/webfactory/WebfactoryPolyglotBundle/workflows/Tests/badge.svg)
![Dependencies](https://github.com/webfactory/WebfactoryPolyglotBundle/workflows/Dependencies/badge.svg)

A bundle to simplify translations for Doctrine entities.

Its main advantages over similar bundles are:

* Transparency: Add translations to existing entities without any API changes.
* Fast: Entity translations are loaded eagerly from separate translation tables.
* Polyglot: Easy access to all available translations of an entity without additional database requests.

[We](https://www.webfactory.de/) use it to create multilingual navigation menus and links like "view this article in
German", where the linked URL has a locale specific slug.

## Installation

Just like any other Symfony bundle, and no additional configuration is required (wheeho!).

## Underlying Data Model and How It Works

The assumption is that you already have a "main" Doctrine entity class with some fields that you now need to make locale-specific.

To do so, we'll add a new _translation entity class_ that contains these fields plus a field for the locale. The main entity and the translation entity class will be connected with a `OneToMany` association. 
 
So, for one single _main_ entity instance, there are zero to many _translation entity_ instances – one for every locale that you have a translation for.
 
This approach reflects our experience that almost always the relevant content (field values) are maintained for a "primary" locale. This is the "authoritative" version of your content/data. Then, this content is translated for one or several "secondary" locales. The _translation entity class_ is concerned with holding this translated data only.

Technically, this bundle sets up a Doctrine event handler (`\Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener`) to
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

The good news for Twig users is that `__toString()` support in Twig is good enough so that you need
 not care about the distinction of strings and translation value holders. So, Twig constructs like `{{ someObject.field }}` or
 `{% if someObject.field is not empty %}...` will work the same regardless of your `getField()` method returns a string 
 value or the translation value holder.
  
You think an example could help clearing up the confusion? Read on!
  
## Usage Example

Let's say you have an existing Doctrine entity `Document` that looks like this:

```php
<?php

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Entity]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private string $text;

    public function getText(): string
    {
        return $this->text;
    }
}
```

Now, we want to make the `text` field translatable.

### Step 1) Update the Main Entity

1. For the main entity class, add the `Webfactory\Bundle\PolyglotBundle\Attribute\Locale` attribute to indicate your
   primary locale. That's the locale you have used for your fields so far.
2. Add the `Webfactory\Bundle\PolyglotBundle\Attribute\Translatable` attribute to all translatable fields. 
3. Add the collection to hold translation instances (more about that in the next section), 
   and add the `Webfactory\Bundle\PolyglotBundle\Attribute\TranslationCollection` attribute to its field. Also make sure it 
   is initialized with an empty Doctrine collection.
4. Change the type hints for the translated fields in the main entity class from `string` to `TranslatableInterface`,
   and use the special `translatable_string` Doctrine column type for it.

The `translatable_string` column type behaves like the built-in `string` type, but allows for type hinting with 
`TranslatableInterface`. If you want it to behave like the `text` type instead, add the `use_text_column` option
like so: `#[ORM\Column(type: "translatable_string", options: ["use_text_column" => true])]`.

This will lead you to something like the following, with some code skipped for brevity:

```php 
<?php

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

#[Polyglot\Locale(primary: "en_GB")]
class Document
{
    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: \DocumentTranslation::class, mappedBy: 'entity')]
    private Collection $translations;

    /**
     * @var TranslatableInterface<string>
     */
    #[Polyglot\Translatable]
    #[ORM\Column(type: 'translatable_string')]
    private TranslatableInterface $text;

    public function __construct(...)
    {
        // ...
        $this->translations = new ArrayCollection();
    }

    public function getText(): string
    {
        return $this->text->translate();
    }
}
```

### Step 2) Create the Translation Entity

1. Create a class for the translation entity. As for the name, we suggest suffixing your main entity's name with
   `Translation`. It has to contain fields for all the fields in your main entity that are to be translated. Declare
   these fields as regular Doctrine ORM column, using plain column types like `text` (e.g. `#[ORM\Column(type: "text")]`).
   You may want to extend `\Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation` to save yourself some boilerplate
   code, but extending this class is not necessary.
2. To implement the one-to-many relationship, the translation entity needs to reference to the original entity. 
   In the following example, this is the `$entity` field.

Your code should look similar to this:

```php
<?php
    
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation;

#[ORM\Table]
#[ORM\UniqueConstraint(columns: ['entity_id', 'locale'])]
#[ORM\Entity]
class DocumentTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    #[Polyglot\Locale]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: \Document::class, inversedBy: 'translations')]
    private Document $entity;

    public function getLocale(): string
    {
        return $this->locale;
    }

    #[ORM\Column]
    private string $text;
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
    public function getText(string $locale = null): string
    {
        return $this->text->translate($locale);    
    }
}
```

... which should be backwards-compatible as well, but allows client code to access the values for locales of their 
choice.

Your last option would be to leave the getter unchanged, return the value holder object and have your client code 
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

## Translations for Doctrine column types other than `string`

In fact, the fields that are used to hold translated values need not use the `translatable` Doctrine column type declaration
and can be of other type than `string`. 

In this case, you need to use a union type for your field type declaration as in the following example.

```php
<?php

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

// ...
class Document
{
    // ... fields and collections omitted for brevity
    #[ORM\Column(type: '...yourtype')]
    #[Polyglot\Translatable]
    private TranslatableInterface|<other type> $text;

    // ...
}
```

With this declaration, the field can serve a dual use: Right before the ORM flushes data, the field value will be switched to the 
value of the "primary" locale, so that the ORM can persist that data as usual. Similarly, after the ORM has loaded the entity,
it will replace the field value with the translation value holder (an instance of `TranslatableInterface`) that you can use
to obtain the translated values as well.

Note that it is not necessary to do this in the translations class (`DocumentTranslation` in the above examples), since that 
class represents the values of a single locale only and never contains `TranslatableInterface` instances.

## Credits, Copyright and License

This Bundle was written by webfactory GmbH, Bonn, Germany. We're a software development agency with a focus on PHP (mostly [Symfony](http://github.com/symfony/symfony)). If you're a developer looking for new challenges, we'd like to hear from you!

- <https://www.webfactory.de>
- <https://twitter.com/webfactory>

Copyright 2012-2024 webfactory GmbH, Bonn. Code released under [the MIT license](LICENSE).
