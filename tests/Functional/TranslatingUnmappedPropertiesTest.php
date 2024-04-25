<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * This test covers that also properties which are not mapped Doctrine fields
 * can be marked as translatable and will be handled by the PolyglotListener.
 *
 * This is useful when these fields are managed or updated by e. g. lifecycle callbacks
 * or other Doctrine event listeners.
 */
class TranslatingUnmappedPropertiesTest extends FunctionalTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([
            TranslatingUnmappedPropertiesTest_Entity::class,
            TranslatingUnmappedPropertiesTest_Translation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new TranslatingUnmappedPropertiesTest_Entity();
        $entity->text = new Translatable('base text');
        $entity->text->setTranslation('Basistext', 'de_DE');

        $this->infrastructure->import($entity);

        $loaded = $this->infrastructure->getEntityManager()->find(TranslatingUnmappedPropertiesTest_Entity::class, $entity->id);

        self::assertSame('Basistext', $loaded->text->translate('de_DE'));
        self::assertSame('base text', $loaded->text->translate('en_GB'));
    }
}

/**
 * @ORM\Entity
 *
 * @ORM\HasLifecycleCallbacks
 */
#[Polyglot\Locale(primary: 'en_GB')]
class TranslatingUnmappedPropertiesTest_Entity
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
     * @ORM\OneToMany(targetEntity="TranslatingUnmappedPropertiesTest_Translation", mappedBy="entity")
     */
    #[Polyglot\TranslationCollection]
    protected Collection $translations;

    /**
     * @ORM\Column(type="string")
     */
    public $mappedText;

    // (!) This field is unmapped from the ORM point of view
    #[Polyglot\Translatable]
    public $text;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->text = new Translatable();
    }

    /** @ORM\PreFlush() */
    public function copyToMappedField(): void
    {
        $this->mappedText = $this->text;
    }

    /** @ORM\PostLoad() */
    public function copyFromMappedField(): void
    {
        $this->text = $this->mappedText;
    }
}

/**
 * @ORM\Entity
 *
 * @ORM\HasLifecycleCallbacks
 */
class TranslatingUnmappedPropertiesTest_Translation
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
     * @ORM\ManyToOne(targetEntity="TranslatingUnmappedPropertiesTest_Entity", inversedBy="translations")
     */
    private TranslatingUnmappedPropertiesTest_Entity $entity;

    /**
     * @ORM\Column
     */
    private $mappedText;

    // (!) This field is unmapped from the ORM point of view
    private $text;

    /** @ORM\PreFlush() */
    public function copyToMappedField(): void
    {
        $this->mappedText = $this->text;
    }

    /** @ORM\PostLoad() */
    public function copyFromMappedField(): void
    {
        $this->text = $this->mappedText;
    }
}
