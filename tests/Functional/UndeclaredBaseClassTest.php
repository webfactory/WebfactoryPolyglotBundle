<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * This test covers a risky pattern where a base class that is neither an entity nor a mapped superclass
 * contains mapped fields, and an entity subclass inherits those.
 *
 * This is not officially supported by Doctrine ORM, but something I've seen quite a few times
 * in practice.
 */
class UndeclaredBaseClassTest extends FunctionalTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([
            UndeclaredBaseClassTest_EntityClass::class,
            UndeclaredBaseClassTest_BaseClassTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new UndeclaredBaseClassTest_EntityClass();
        $t1 = new Translatable('base text');
        $t1->setTranslation('Basistext', 'de_DE');
        $entity->setText($t1);

        $this->infrastructure->import($entity);

        $loaded = $this->infrastructure->getEntityManager()->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());

        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->infrastructure->getEntityManager();
        $entity = new UndeclaredBaseClassTest_EntityClass();
        $entity->setText(new Translatable('base text'));
        $this->infrastructure->import($entity);

        $loaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->infrastructure->getEntityManager();

        $entity = new UndeclaredBaseClassTest_EntityClass();
        $t1 = new Translatable('base text');
        $t1->setTranslation('Basistext', 'de_DE');
        $entity->setText($t1);
        $this->infrastructure->import($entity);

        $loaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());

        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
    }
}

/**
 * Fields in this class cannot be "private" - otherwise, they would not be picked up by the
 * Doctrine mapping drivers when processing the entity sub-class (UndeclaredBaseClassTest_EntityClass).
 */
class UndeclaredBaseClassTest_BaseClass
{
    
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: \UndeclaredBaseClassTest_BaseClassTranslation::class, mappedBy: 'entity')]
    protected Collection $translations;

    #[Polyglot\Translatable]
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
class UndeclaredBaseClassTest_BaseClassTranslation
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: \UndeclaredBaseClassTest_EntityClass::class, inversedBy: 'translations')]
    private UndeclaredBaseClassTest_EntityClass $entity;

    #[ORM\Column]
    private string $text;
}

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class UndeclaredBaseClassTest_EntityClass extends UndeclaredBaseClassTest_BaseClass
{
}
