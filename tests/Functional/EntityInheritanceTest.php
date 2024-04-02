<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * This tests translations for different fields in an inheritance hierarchy. For every
 * entity class in the hierarchy, a dedicated translations class has to be used.
 */
class EntityInheritanceTest extends FunctionalTestBase
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

        $loaded = $this->infrastructure->getEntityManager()->find(EntityInheritance_ChildEntityClass::class, $entity->getId());

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

        $loaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $loaded->getExtraText()->setTranslation('Extratext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());

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

        $loaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $loaded->getExtraText()->setTranslation('new extra text');
        $loaded->getExtraText()->setTranslation('neuer Extratext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());

        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
        self::assertSame('neuer Extratext', $reloaded->getExtraText()->translate('de_DE'));
        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('new extra text', $reloaded->getExtraText()->translate('en_GB'));
    }
}


#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
#[ORM\InheritanceType(value: 'SINGLE_TABLE')]
#[ORM\DiscriminatorMap(['base' => 'EntityInheritance_BaseEntityClass', 'child' => 'EntityInheritance_ChildEntityClass'])]
#[ORM\DiscriminatorColumn(name: 'discriminator', type: 'string')]
class EntityInheritance_BaseEntityClass
{
    
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    private string $discriminator;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: \EntityInheritance_BaseEntityClassTranslation::class, mappedBy: 'entity')]
    private Collection $translations;

    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    private TranslatableInterface|string|null $text = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setText(TranslatableInterface $text): void
    {
        $this->text = $text;
    }

    public function getText(): TranslatableInterface
    {
        return $this->text;
    }
}

#[ORM\Entity]
class EntityInheritance_BaseEntityClassTranslation
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: \EntityInheritance_BaseEntityClass::class, inversedBy: 'translations')]
    private EntityInheritance_BaseEntityClass $entity;

    #[ORM\Column]
    private string $text;
}

#[ORM\Entity]
class EntityInheritance_ChildEntityClass extends EntityInheritance_BaseEntityClass
{
    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    private TranslatableInterface|string|null $extraText = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: \EntityInheritance_ChildEntityClassTranslation::class, mappedBy: 'entity')]
    private Collection $extraTranslations;

    public function __construct()
    {
        parent::__construct();
        $this->extraTranslations = new ArrayCollection();
    }

    public function setExtra(TranslatableInterface $extraText): void
    {
        $this->extraText = $extraText;
    }

    public function getExtraText(): TranslatableInterface
    {
        return $this->extraText;
    }
}

#[ORM\Entity]
class EntityInheritance_ChildEntityClassTranslation
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: \EntityInheritance_ChildEntityClass::class, inversedBy: 'extraTranslations')]
    private EntityInheritance_ChildEntityClass $entity;

    #[ORM\Column]
    private string $extraText;
}
