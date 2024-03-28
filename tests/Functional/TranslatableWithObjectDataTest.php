<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PersistentTranslatable;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * This tests shows that the translatable properties can even be objects.
 * Cave: Doctrine change tracking works only for changing objects to new
 * instances. It does not compare changed values of objects in "object" type columns.
 */
class TranslatableWithObjectDataTest extends FunctionalTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([
            TranslatableWithObjectDataTest_Entity::class,
            TranslatableWithObjectDataTest_Translation::class,
        ]);
    }

    /**
     * @test
     */
    public function persist_new_data_keeps_entity_values_as_translatable(): void
    {
        $entity = new TranslatableWithObjectDataTest_Entity();
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text en_GB'));
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text de_DE'), 'de_DE');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertInstanceOf(PersistentTranslatable::class, $entity->data);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $entity->data->translate('de_DE'));
        self::assertSame('text de_DE', $entity->data->translate('de_DE')->text);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $entity->data->translate('en_GB'));
        self::assertSame('text en_GB', $entity->data->translate('en_GB')->text);
    }

    /**
     * @test
     */
    public function persist_and_load_new_data_provides_translatables(): void
    {
        $entity = new TranslatableWithObjectDataTest_Entity();
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text en_GB'));
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text de_DE'), 'de_DE');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reload = $this->entityManager->find(TranslatableWithObjectDataTest_Entity::class, $entity->id);

        self::assertInstanceOf(PersistentTranslatable::class, $reload->data);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $reload->data->translate('de_DE'));
        self::assertSame('text de_DE', $reload->data->translate('de_DE')->text);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $reload->data->translate('en_GB'));
        self::assertSame('text en_GB', $reload->data->translate('en_GB')->text);
    }

    /**
     * @test
     */
    public function flushing_updates_keeps_entity_values_as_translatable(): void
    {
        $entity = new TranslatableWithObjectDataTest_Entity();
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text en_GB'));
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text de_DE'), 'de_DE');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Doctrine ORM change tracking for objects requires new object instances here
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('updated text en_GB'));
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('updated text de_DE'), 'de_DE');
        $this->entityManager->flush();

        self::assertInstanceOf(PersistentTranslatable::class, $entity->data);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $entity->data->translate('de_DE'));
        self::assertSame('updated text de_DE', $entity->data->translate('de_DE')->text);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $entity->data->translate('en_GB'));
        self::assertSame('updated text en_GB', $entity->data->translate('en_GB')->text);
    }

    /**
     * @test
     */
    public function flushing_updates_and_reloading_provides_translatables(): void
    {
        $entity = new TranslatableWithObjectDataTest_Entity();
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text en_GB'));
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('text de_DE'), 'de_DE');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Doctrine ORM change tracking for objects requires new object instances here
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('updated text en_GB'));
        $entity->data->setTranslation(new TranslatableWithObjectDataTest_Object('updated text de_DE'), 'de_DE');
        $this->entityManager->flush();

        $this->entityManager->clear();
        $reload = $this->entityManager->find(TranslatableWithObjectDataTest_Entity::class, $entity->id);

        self::assertInstanceOf(PersistentTranslatable::class, $reload->data);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $reload->data->translate('de_DE'));
        self::assertSame('updated text de_DE', $reload->data->translate('de_DE')->text);
        self::assertInstanceOf(TranslatableWithObjectDataTest_Object::class, $reload->data->translate('en_GB'));
        self::assertSame('updated text en_GB', $reload->data->translate('en_GB')->text);
    }
}

/**
 * @ORM\Entity
 */
#[Polyglot\Locale(primary: 'en_GB')]
class TranslatableWithObjectDataTest_Entity
{
    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     */
    public ?int $id = null;

    /**
     * @ORM\OneToMany(targetEntity="TranslatableWithObjectDataTest_Translation", mappedBy="entity")
     */
    #[Polyglot\TranslationCollection]
    public Collection $translations;

    /**
     * @ORM\Column(type="object")
     */
    #[Polyglot\Translatable]
    public TranslatableInterface|TranslatableWithObjectDataTest_Object $data;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->data = new Translatable();
    }
}

/**
 * @ORM\Entity
 */
class TranslatableWithObjectDataTest_Translation
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column
     */
    #[Polyglot\Locale]
    private string $locale;

    /**
     * @ORM\ManyToOne(targetEntity="TranslatableWithObjectDataTest_Entity", inversedBy="translations")
     */
    private TranslatableWithObjectDataTest_Entity $entity;

    /**
     * @ORM\Column(type="object")
     */
    private TranslatableWithObjectDataTest_Object $data;
}

class TranslatableWithObjectDataTest_Object
{
    public string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }
}
