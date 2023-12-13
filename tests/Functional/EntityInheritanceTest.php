<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

class EntityInheritanceTest extends BaseFunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([
            EntityInheritance_BaseEntityClass::class,
            EntityInheritance_BaseEntityClassTranslation::class,
            EntityInheritance_ChildEntityClass::class,
            EntityInheritance_ChildEntityClassTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new EntityInheritance_ChildEntityClass();
        $t1 = new Translatable('base text');
        $t1->setTranslation('Basistext', 'de_DE');
        $entity->setText($t1);

        $t2 = new Translatable('extra text');
        $t2->setTranslation('Extratext', 'de_DE');
        $entity->setExtra($t2);

        $this->infrastructure->import($entity);

        $loaded = $this->infrastructure->getEntityManager()->find(EntityInheritance_ChildEntityClass::class, $entity->id);

        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
        self::assertSame('Extratext', $loaded->getExtraText()->translate('de_DE'));
        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
        self::assertSame('extra text', $loaded->getExtraText()->translate('en_GB'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->infrastructure->getEntityManager();
        $entity = new EntityInheritance_ChildEntityClass();
        $entity->setText(new Translatable('base text'));
        $entity->setExtra(new Translatable('extra text'));
        $this->infrastructure->import($entity);

        $loaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->id);
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $loaded->getExtraText()->setTranslation('Extratext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->id);

        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
        self::assertSame('Extratext', $reloaded->getExtraText()->translate('de_DE'));
        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('extra text', $reloaded->getExtraText()->translate('en_GB'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->infrastructure->getEntityManager();

        $entity = new EntityInheritance_ChildEntityClass();
        $t1 = new Translatable('old base text');
        $t1->setTranslation('alter Basistext', 'de_DE');
        $entity->setText($t1);

        $t2 = new Translatable('old extra text');
        $t2->setTranslation('alter Extratext', 'de_DE');
        $entity->setExtra($t2);

        $this->infrastructure->import($entity);

        $loaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->id);
        $oed = $entityManager->getUnitOfWork()->getOriginalEntityData($loaded);
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $loaded->getExtraText()->setTranslation('new extra text');
        $loaded->getExtraText()->setTranslation('neuer Extratext', 'de_DE');
        $oed = $entityManager->getUnitOfWork()->getOriginalEntityData($loaded);
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->id);

        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
        self::assertSame('neuer Extratext', $reloaded->getExtraText()->translate('de_DE'));
        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('new extra text', $reloaded->getExtraText()->translate('en_GB'));
    }
}

/**
 * @ORM\Entity()
 * @ORM\InheritanceType(value="SINGLE_TABLE")
 * @ORM\DiscriminatorMap({"base"="EntityInheritance_BaseEntityClass", "child"="EntityInheritance_ChildEntityClass"})
 * @ORM\DiscriminatorColumn(name="discriminator", type="string")
 * @Polyglot\Locale(primary="en_GB")
 */
class EntityInheritance_BaseEntityClass
{
    /**
     * @ORM\Column
     * @ORM\Id
     * @ORM\GeneratedValue()
     */
    public int $id;

    public string $discriminator;

    /**
     * @ORM\OneToMany(targetEntity="EntityInheritance_BaseEntityClassTranslation", mappedBy="entity", cascade={"persist"})
     * @Polyglot\TranslationCollection
     */
    public $translations;

    /**
     * @ORM\Column(type="string")
     * @Polyglot\Translatable
     *
     * @var TranslatableInterface|string|null
     */
    public $text = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function getText()
    {
        return $this->text;
    }
}

/**
 * @ORM\Entity
 */
class EntityInheritance_BaseEntityClassTranslation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column
     */
    public int $id;

    /**
     * @ORM\Column
     * @Polyglot\Locale
     */
    public string $locale;

    /**
     * @ORM\ManyToOne(targetEntity="EntityInheritance_BaseEntityClass", inversedBy="translations")
     */
    public $entity;

    /**
     * Contains the translation.
     *
     * Must be protected to be usable when this class is used as base for a mock.
     *
     * @ORM\Column(type="string")
     *
     * @var string
     */
    public $text;
}

/**
 * @ORM\Entity
 */
class EntityInheritance_ChildEntityClass extends EntityInheritance_BaseEntityClass
{
    /**
     * @ORM\Column(type="string")
     * @Polyglot\Translatable
     *
     * @var TranslatableInterface|string|null
     */
    public $extraText = null;

    /**
     * @ORM\OneToMany(targetEntity="EntityInheritance_ChildEntityClassTranslation", mappedBy="entity", cascade={"persist"})
     * @Polyglot\TranslationCollection
     */
    public $extraTranslations;

    public function __construct()
    {
        parent::__construct();
        $this->extraTranslations = new ArrayCollection();
    }

    public function setExtra($extraText)
    {
        $this->extraText = $extraText;
    }

    public function getExtraText()
    {
        return $this->extraText;
    }
}

/**
 * @ORM\Entity
 */
class EntityInheritance_ChildEntityClassTranslation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column
     */
    public int $id;

    /**
     * @ORM\Column
     * @Polyglot\Locale
     */
    public string $locale;

    /**
     * @ORM\ManyToOne(targetEntity="EntityInheritance_ChildEntityClass", inversedBy="extraTranslations")
     */
    public $entity;

    /**
     * Contains the translation.
     *
     * Must be protected to be usable when this class is used as base for a mock.
     *
     * @ORM\Column(type="string")
     *
     * @var string
     */
    public $extraText;
}
