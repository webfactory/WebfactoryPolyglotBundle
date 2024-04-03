<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * This test shows that `PersistentTranslatable` will take care of adding new
 * translation entities to the entity manager, so explicit `persist()` calls are not
 * necessary; and also, `cascade={"persist"}` isn't either.
 */
class CascadePersistTranslationsTest extends FunctionalTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([
            CascadePersistTranslationsTest_Entity::class,
            CascadePersistTranslationsTest_Translation::class,
        ]);
    }

    /**
     * @test
     */
    public function adding_and_persisting_translations(): void
    {
        $entity = new CascadePersistTranslationsTest_Entity();
        $entity->addTranslation('de_DE', 'text de_DE');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // note the absent call to $this->entityManager->persist() for the translation entity

        $result = $this->entityManager->getConnection()->executeQuery('SELECT * FROM CascadePersistTranslationsTest_Translation')->fetchAllAssociative();

        self::assertCount(1, $result);
        self::assertSame('text de_DE', $result[0]['text']);
    }
}

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class CascadePersistTranslationsTest_Entity
{
    
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * (!) There is *not* cascade="persist" configuration here.
     */
    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: \CascadePersistTranslationsTest_Translation::class, mappedBy: 'entity')]
    protected Collection $translations;

    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    protected string|TranslatableInterface $text;

    public function __construct()
    {
        $this->text = new Translatable('test en_GB');
        $this->translations = new ArrayCollection();
    }

    public function addTranslation(string $locale, mixed $text): void
    {
        $this->text->setTranslation($text, $locale);
    }
}

#[ORM\Entity]
class CascadePersistTranslationsTest_Translation
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: \CascadePersistTranslationsTest_Entity::class, inversedBy: 'translations')]
    private CascadePersistTranslationsTest_Entity $entity;

    #[ORM\Column]
    private string $text;
}
