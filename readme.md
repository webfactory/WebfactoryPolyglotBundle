webfactory/polyglot-bundle
==========================

[![Build Status](https://travis-ci.org/webfactory/polyglot-bundle.svg?branch=master)](https://travis-ci.org/webfactory/polyglot-bundle)
[![Coverage Status](https://coveralls.io/repos/webfactory/polyglot-bundle/badge.png?branch=master)](https://coveralls.io/r/webfactory/polyglot-bundle?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/webfactory/polyglot-bundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/webfactory/polyglot-bundle/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/85a47687-38e7-41ca-9483-6698fb7e4ca8/mini.png)](https://insight.sensiolabs.com/projects/85a47687-38e7-41ca-9483-6698fb7e4ca8)

A bundle to simplify translations for Doctrine entities.

Its main advantages over similar bundles are:

* Transparency: Add translations to existing entities without any API changes.
* Fast: Entity translations are loaded eagerly from separate translation tables.
* Polyglot: Easy access to all available translations of an entity without additional database requests.

[We](https://www.webfactory.de/) use it to create multilingual navigation menus and links like "view this article in
German", where the linked URL has a locale specific slug.

If you're fine with the [known limitations](#known-limitations), read on!


Installation
------------

### Step 1) Get the bundle via Composer
Add the following to your composer.json (see http://getcomposer.org/):

    "require" :  {
        // ...
        "webfactory/polyglot-bundle": "@stable"
    }

### Step 2) Enable the bundle in `app/AppKernel.php`:

    <?php
    // app/AppKernel.php
    
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Webfactory\Bundle\PolyglotBundle\WebfactoryPolyglotBundle(),
        );
    }


Data Model
----------

A main Doctrine entity has some are locale specific fields. It is marked with a "primary locale" indicating it contains
texts in the fallback locale.

The main entity is associated with a number of translation entities containing the translated texts (and some metadata,
see example below), one for each translated locale.


Usage Example
-------------

Let's say you have an existing Doctrine entity `Document` that looks like this:

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
        protected $id;
    
        /**
         * @ORM\Column(type="text")
         * @var string
         */
        protected $text;

        /**
         * @return string
         */
        public function getText()
        {
            return $this->text;
        }
    
        /**
         * @param string
         */
        public function setText()
        {
            return $this->text;
        }
    }


And now we want to make the `text` translatable.

### Step 1) Update the Main Entity

1. Annotate the main entity with the primary locale (in this case, the language of the database field `document.text`)
   with `Webfactory\Bundle\PolyglotBundle\Annotation\Locale`.
2. Annotate all translatable fields with `Webfactory\Bundle\PolyglotBundle\Annotation\Translatable`.
3. Add the association for the upcoming translation, and annotate it's field with
   `Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection` and make sure it's initialized with an empty
   Doctrine collection.
4. You may want to change your type hint for the translated fields from string to string|TranslatableInterface and cast
   to string in your getters (more on that in the [Magic Explained](#magic-explained) section).

... and you will get something like this:

    <?php

    ...
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
        protected $translations;

        /**
         * @Polyglot\Translatable
         * @var string|TranslatableInterface
         */
        protected $text;

        public function __construct($text)
        {
            $this->translations = new ArrayCollection();
        }

        /**
         * @return string
         */
        public function getText()
        {
            return (string) $this->text;
        }
    }


### Step 2) Create the Translation Entity

1. Create a class for the translation entity. As for the name, we suggest suffixing your main entity's name with
   "Translation". It has to contain all translated properties with regular Doctrine annotations
   (e.g. @ORM\Column(type="text")).
2. If you choose to name your back reference `entity` as we did in the example, you may want to extend
   `\Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation` to save yourself the hassle of rewriting some meta data
   and a getter. But extending not necessary!
3. The translation entity needs to have the Doctrine back reference to the original entity, in our example the `$entity`
   property.

... and you will end up with something like this:

    <?php
        
    use Doctrine\ORM\Mapping as ORM;
    use Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation;
    
    /**
     * @ORM\Entity
     * @ORM\Table(
     *      uniqueConstraints = {
     *          @ORM\UniqueConstraint(name="lookup_unique_idx", columns={"entity_id", "locale"})
     *     }
     * )
     */
    class DocumentTranslation extends BaseTranslation
    {
        /**
         * @ORM\Column(type="text")
         * @var string
         */
        protected $text;

        /**
         * @ORM\ManyToOne(targetEntity="Document", inversedBy="_translations")
         * @var Document
         */
        protected $entity;
    }


### Step 3) Update your database schema

Use Doctrine to update your database schema, e.g. via the
[DoctrineMigrationsBundle](https://github.com/Doctrine/DoctrineMigrationsBundle).

### That's it!

Your entities will now be automatically loaded in the language corresponding to the current request's locale. If there
is no translation for the current locale, the primary locale is used as a fallback.

If you have a getText() method without the (string) cast from above, you can retrieve specific translations like this:

    $document->getText()->translate('de_DE')


Magic Explained
---------------
This bundle does its magic by integrating the Doctrine listener
`\Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener` into the Symfony stack, which provides the request locale.

The main idea of the listener is to hook into
[Doctrine's lifecycle events](http://doctrine-orm.readthedocs.org/en/latest/reference/events.html) to replace string
values in annotated fields with `\Webfactory\Bundle\PolyglotBundle\TranslatableInterface`s. Their implementations use
the request's locale to provide the matching translation in their __toString() method, besides offering the other
translations as well.


Known Limitations
-----------------

It's not very comfortable to persist entities and their translations. One might think: it's just Doctrine, the
translation entity is the owning side of a cascade={"persist"}-association, so I'll just persist the translation. But
no - we seem to have broken Doctrine's lifecycle management here. As a workaround, you can persist both the main entity
and it's translation entities. See the tests for further details.


Planned Features / Wish List
----------------------------

For now, each entity has one fixed primary locale. We have encountered cases in which some records were only available
in a language different from the primary locale. Therefore, we want to remove the primary locale annotation and store
this information in an attribute. This would allow each record to have its own primary locale.
