webfactory/polyglot-bundle
==========================

A bundle to simplify translations for Doctrine entities.

Features
--------
* Transparency: add translations to existing entities without any API changes
* Fast: entity translations are loaded eagerly from separate translation tables
* Easy access to all available translations of an entity


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


Data model
----------

A main doctrine entity (e.g. `Document`) contains all fields of the entity of which some are language specific. We
distinguish between two types of translations: one "master translation" and any number of "secondary translations":

* The master translation is stored in the main entity (`Document`) itself. This is why the main entity is marked with a
"primary locale" which indicates the language of the master translation.
* The secondary translations are stored in a separate doctrine entity (e.g. `DocumentTranslation`) that contains only
the language specific fields (and some metadata, see example below).

Both entities are connected via doctrine relations.


Usage example
-------------

Say you have an existing doctrine-entity `Document` that looks like this:

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
	     */
	    protected $id;
	
	    /**
	     * @ORM\Column(type="text")
	     */
	    protected $text;

	    //... getters & setters
	}


And now we want to make the `text` translatable.

### Step 1) Create the translation entity

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
* The translation entity needs to have a property $entity which is mapped (via doctrine relation) to the original entity

### Step 2) Update the main entity

	<?php

	use Doctrine\ORM\Mapping as ORM;
	use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
	
	/**
	 * @ORM\Entity()
	 * @ORM\Table()
	 * @Polyglot\Locale(primary="en_GB")
	 */
	class Document
	{
	    /**
	     * @ORM\Id
	     * @ORM\GeneratedValue
	     * @ORM\Column(type="integer") */
	    protected $id;
	
	    /**
	     * @ORM\OneToMany(targetEntity="DocumentTranslation", mappedBy="entity")
	     * @Polyglot\TranslationCollection
	     */
	    protected $_translations;
	
	    /**
	     * @ORM\Column(type="text")
	     * @Polyglot\Translatable
	     */
	    protected $text;
	    
	    public function getText()
	    {
	        return $this->text;
        }
	}

**Note**:

* Set the primary locale of the main entity (in this case, the language of the database field `document.text`) via the Webfactory\Bundle\PolyglotBundle\Annotation\Locale
* All translateable fields need to be marked with the `Webfactory\Bundle\PolyglotBundle\Annotation\Translateable` annotation
* The doctrine relation to the translation-entity needs to be mapped and the property needs to be marked with the `Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection` annotation

### Step 3) Update your database schema

For example using doctrine-migrations-bundle.


**That's it.**
Your entities will now automatically be loaded in the language corresponding to the current request's locale.
If there is no translation for the current locale, the primary locale is used as a fallback.

You can retrieve a specific translation like this:
	
	$document->getText()->translate('de_DE')


Planned features/wish list
--------------------------

* Each entity can only have one fixed primary locale. 
  We have encountered cases in which some record were only available in a language different from the primary locale.
  Therefore, we want to remove the annotation for the primary locale and store this information in the database. This allows each record to have its own primary locale.

