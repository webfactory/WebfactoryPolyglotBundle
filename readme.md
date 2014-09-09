webfactory/polyglot-bundle
==========================

A bundle to simplify translations for Doctrine entities. It's main advantages over similar bundles are:

* Transparency: Add translations to existing entities without any API changes.
* Fast: Entity translations are loaded eagerly from separate translation tables.
* Polyglot: Easy access to all available translations of an entity without additional database requests.

[We](https://www.webfactory.de/) use it to create multilingual navigation menus and links like "view this article in
German", where the linked URL has a locale specific slug.

If you're fine with the [known limitations](#KnownLimitations), read on!


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
2. Annotate all translatable fields with `Webfactory\Bundle\PolyglotBundle\Annotation\Translateable`.
3. Add the association for the upcoming translation entity and annotate it's field with
   `Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection`.
4. You may want to change your type hint for the translated fields from string to string|TranslatableInterface and cast
   to string in your getters (more on that later).

... and you will get something like this:

    <?php

    ...
    use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
    use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;
    use Doctrine\Common\Collections\Collection;

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

        /**
         * @return string
         */
        public function getText()
        {
            return (string) $this->text;
        }
    }


### Step 2) Create the translation entity

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
         */
        protected $text;

        /**
         * @ORM\ManyToOne(targetEntity="Document", inversedBy="_translations")
         */
        protected $entity;
    }

**Note**:

* The translation entity needs to have all properties that will be translated
* The translation entity doesn't need to extend `\Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation`, it's just
  comfortable
* The translation entity needs to have a property $entity which is mapped (via Doctrine relation) to the original entity


### Step 3) Update your database schema

Use Doctrine zo update your database schema, e.g. via the
[DoctrineMigrationsBundle](https://github.com/Doctrine/DoctrineMigrationsBundle).


**That's it.**
Your entities will now automatically be loaded in the language corresponding to the current request's locale.
If there is no translation for the current locale, the primary locale is used as a fallback.

You can retrieve a specific translation like this:

    $document->getText()->translate('de_DE')


Known Limitations
-----------------

It's not very comfortable to persist entities and their translations. One might think: it's just Doctrine, the
translation entity is the owning side of a cascade={"persist"}-association, so I'll just persist the translation. But no
- we seem to have broken Doctrine's lifecycle management here. As a workaround, you can persist both the main entity and
it's translation entities. See the tests for further details.


Planned features/wish list
--------------------------

* Each entity can only have one fixed primary locale. 
  We have encountered cases in which some record were only available in a language different from the primary locale.
  Therefore, we want to remove the annotation for the primary locale and store this information in the database. This allows each record to have its own primary locale.
