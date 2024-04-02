<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * This tests a setup where the "translation" field is named different from the
 * field in the base entity class.
 */
class TranslationPropertyNamedDifferentlyTest extends FunctionalTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([
            TranslationPropertyNamedDifferently_Entity::class,
            TranslationPropertyNamedDifferently_Translation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new TranslationPropertyNamedDifferently_Entity();
        $translatable = new Translatable('base text');
        $translatable->setTranslation('Basistext', 'de_DE');
        $entity->setText($translatable);

        $this->infrastructure->import($entity);

        $loaded = $this->infrastructure->getEntityManager()->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());

        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->infrastructure->getEntityManager();
        $entity = new TranslationPropertyNamedDifferently_Entity();
        $entity->setText(new Translatable('base text'));
        $this->infrastructure->import($entity);

        $loaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->infrastructure->getEntityManager();

        $entity = new TranslationPropertyNamedDifferently_Entity();
        $translatable = new Translatable('base text');
        $translatable->setTranslation('Basistext', 'de_DE');
        $entity->setText($translatable);
        $this->infrastructure->import($entity);

        $loaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());

        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
    }
}

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class TranslationPropertyNamedDifferently_Entity
{
    
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: \TranslationPropertyNamedDifferently_Translation::class, mappedBy: 'entity')]
    protected Collection $translations;

    #[Polyglot\Translatable(translationFieldname: 'textOtherName')]
    #[ORM\Column(type: 'string')]
    protected string|TranslatableInterface|null $text = null;

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

    public function getText(): ?TranslatableInterface
    {
        return $this->text;
    }
}

#[ORM\Entity]
class TranslationPropertyNamedDifferently_Translation
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: \TranslationPropertyNamedDifferently_Entity::class, inversedBy: 'translations')]
    private TranslationPropertyNamedDifferently_Entity $entity;

    #[ORM\Column]
    private string $textOtherName;
}
