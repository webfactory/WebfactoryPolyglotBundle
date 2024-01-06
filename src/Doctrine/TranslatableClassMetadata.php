<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Webfactory\Bundle\PolyglotBundle\Annotation;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * For an entity class that contains @Translatable annotations, this class holds metadata
 * like which fields in the class are @Translatable_s, which field holds the collection
 * of translations etc. There need only be one instance of this class for every
 * entity class with translations.
 */
final class TranslatableClassMetadata
{
    /**
     * Ein Mapping von Feldnamen in der Hauptklasse auf die Felder in der
     * Übersetzungs-Klasse, in denen die jeweilige Übersetzung liegt.
     *
     * @var array<string, ReflectionProperty>
     */
    private array $translationFieldMapping = [];

    /**
     * Die Eigenschaften der Haupt-Klasse, die übersetzbar sind; indiziert nach Feldnamen.
     *
     * @var array<string, ReflectionProperty>
     */
    private array $translatedProperties = [];

    /**
     * Die Eigenschaft der Haupt-Klasse, die die Collection der Übersetzungen hält.
     */
    private ?ReflectionProperty $translationsCollectionProperty = null;

    /**
     * Die Eigenschaft der Übersetzungs-Klasse, die als many-to-one auf die Haupt-Klasse verweist.
     */
    private ?ReflectionProperty $translationMappingProperty = null;

    /**
     * Die Eigenschaft in der Übersetzungs-Klasse, die die Sprache einer Übersetzungsinstanz enhtält.
     */
    private ?ReflectionProperty $translationLocaleProperty = null;

    /**
     * Die Übersetzungs-Klasse.
     */
    private ?ReflectionClass $translationClass = null;

    /**
     * Die Locale der Werte in der Haupt-Klasse.
     */
    private ?string $primaryLocale = null;

    private ?LoggerInterface $logger = null;

    /**
     * @param class-string $class The FQCN for the entity class whose translatable fields are described by this
     *                            TranslatableClassMetadata instance. If the class has base entity classes (or mapped
     *                            superclasses), a separate instance of TranslatableClassMetadata will be used for
     *                            their fields.
     */
    private function __construct(
        private readonly string $class
    ) {
    }

    public static function parseFromClass(string $class, Reader $reader, ClassMetadataFactory $classMetadataFactory): ?self
    {
        /** @var ClassMetadataInfo $cm */
        $cm = $classMetadataFactory->getMetadataFor($class);

        $tm = new static($class);
        $tm->findPrimaryLocale($cm, $reader);
        $tm->findTranslationsCollection($cm, $reader, $classMetadataFactory);
        $tm->findTranslatedProperties($cm, $reader, $classMetadataFactory);

        if ($tm->assertNoAnnotationsArePresent()) {
            return null;
        }
        $tm->assertAnnotationsAreComplete($class);

        return $tm;
    }

    public function setLogger(LoggerInterface $logger = null): void
    {
        $this->logger = $logger;
    }

    public function sleep(): SerializedTranslatableClassMetadata
    {
        $sleep = new SerializedTranslatableClassMetadata();
        $sleep->class = $this->class;
        $sleep->primaryLocale = $this->primaryLocale;
        $sleep->translationClass = $this->translationClass->name;

        foreach ($this->translationFieldMapping as $fieldname => $property) {
            $sleep->translationFieldMapping[$fieldname] = [$property->class, $property->name];
        }

        foreach ($this->translatedProperties as $fieldname => $property) {
            $sleep->translatedProperties[$fieldname] = [$property->class, $property->name];
        }

        $sleep->translationLocaleProperty = [$this->translationLocaleProperty->class, $this->translationLocaleProperty->name];
        $sleep->translationsCollectionProperty = [$this->translationsCollectionProperty->class, $this->translationsCollectionProperty->name];
        $sleep->translationMappingProperty = [$this->translationMappingProperty->class, $this->translationMappingProperty->name];

        return $sleep;
    }

    public static function wakeup(SerializedTranslatableClassMetadata $data): self
    {
        $self = new self($data->class);
        $self->primaryLocale = $data->primaryLocale;
        $self->translationClass = new ReflectionClass($data->translationClass);

        foreach ($data->translationFieldMapping as $fieldname => $property) {
            $self->translationFieldMapping[$fieldname] = new ReflectionProperty(...$property);
        }

        foreach ($data->translatedProperties as $fieldname => $property) {
            $self->translatedProperties[$fieldname] = new ReflectionProperty(...$property);
        }

        $self->translationsCollectionProperty = new ReflectionProperty(...$data->translationsCollectionProperty);
        $self->translationMappingProperty = new ReflectionProperty(...$data->translationMappingProperty);
        $self->translationLocaleProperty = new ReflectionProperty(...$data->translationLocaleProperty);

        return $self;
    }

    private function assertNoAnnotationsArePresent(): bool
    {
        return null === $this->translationClass
            && null === $this->translationLocaleProperty
            && null === $this->translationMappingProperty
            && 0 === \count($this->translatedProperties)
            && null === $this->primaryLocale;
    }

    private function assertAnnotationsAreComplete(string $class): void
    {
        if (null === $this->translationClass) {
            throw new RuntimeException('The annotation with the translation class name is missing or incorrect, e.g. @ORM\OneToMany(targetEntity="TestEntityTranslation", ...)');
        }

        if (null === $this->translationLocaleProperty) {
            throw new RuntimeException('The @Polyglot\Locale annotation at the language property of the translation class is missing or incorrect');
        }

        if (null === $this->translationMappingProperty) {
            throw new RuntimeException('The attribute referenced in the mappedBy-Attribute of the @ORM\OneToMany(..., mappedBy="...") is missing or incorrect');
        }

        if (0 === \count($this->translatedProperties)) {
            throw new RuntimeException('No translatable attributes annotated with @Polyglot\Translatable were found');
        }

        if (null === $this->primaryLocale) {
            throw new RuntimeException('A primary locale has to be set at the class level for '.$class);
        }
    }

    private function findTranslatedProperties(ClassMetadataInfo $cm, Reader $reader, ClassMetadataFactory $classMetadataFactory): void
    {
        if (!$this->translationClass) {
            return;
        }

        $translationClassMetadata = $classMetadataFactory->getMetadataFor($this->translationClass->getName());

        foreach ($cm->fieldMappings as $fieldName => $mapping) {
            if (isset($mapping['declared'])) {
                // The association is inherited from a parent class
                continue;
            }

            $reflectionProperty = $cm->getReflectionClass()->getProperty($fieldName);

            $annotation = $reader->getPropertyAnnotation(
                $reflectionProperty,
                Annotation\Translatable::class
            );

            if ($annotation) {
                $translationFieldname = $annotation->getTranslationFieldname() ?: $fieldName;
                $translationFieldReflectionProperty = $translationClassMetadata->getReflectionProperty($translationFieldname);

                $this->translatedProperties[$fieldName] = $reflectionProperty;
                $this->translationFieldMapping[$fieldName] = $translationFieldReflectionProperty;
            }
        }
    }

    private function findTranslationsCollection(ClassMetadataInfo $cm, Reader $reader, ClassMetadataFactory $classMetadataFactory): void
    {
        foreach ($cm->associationMappings as $fieldName => $mapping) {
            if (isset($mapping['declared'])) {
                // The association is inherited from a parent class
                continue;
            }

            $annotation = $reader->getPropertyAnnotation(
                $cm->getReflectionProperty($fieldName),
                Annotation\TranslationCollection::class
            );

            if ($annotation) {
                $this->translationsCollectionProperty = $cm->getReflectionClass()->getProperty($fieldName);

                $translationEntityMetadata = $classMetadataFactory->getMetadataFor($mapping['targetEntity']);
                $this->translationClass = $translationEntityMetadata->getReflectionClass();
                $this->translationMappingProperty = $translationEntityMetadata->getReflectionProperty($mapping['mappedBy']);
                $this->parseTranslationsEntity($reader, $translationEntityMetadata);

                return;
            }
        }
    }

    private function findPrimaryLocale(ClassMetadataInfo $cm, Reader $reader): void
    {
        foreach (array_merge([$cm->name], $cm->parentClasses) as $class) {
            $annotation = $reader->getClassAnnotation(new ReflectionClass($class), Annotation\Locale::class);
            if (null !== $annotation) {
                $this->primaryLocale = $annotation->getPrimary();

                return;
            }
        }
    }

    private function parseTranslationsEntity(Reader $reader, ClassMetadataInfo $cm): void
    {
        foreach ($cm->fieldMappings as $fieldName => $mapping) {
            $reflectionProperty = $cm->getReflectionProperty($fieldName);

            $annotation = $reader->getPropertyAnnotation(
                $reflectionProperty,
                Annotation\Locale::class
            );

            if ($annotation) {
                $this->translationLocaleProperty = $reflectionProperty;

                return;
            }
        }
    }

    /**
     * For a given entity, find all @Translatable fields that contain new (not yet persisted)
     * Translatable objects and replace those with PersistentTranslatable.
     */
    public function injectPersistentTranslatables(object $entity, EntityManager $entityManager, DefaultLocaleProvider $defaultLocaleProvider): void
    {
        foreach ($this->translatedProperties as $fieldName => $property) {
            new PersistentTranslatable(
                $entityManager->getUnitOfWork(),
                $this->class,
                $entity,
                $this->primaryLocale,
                $defaultLocaleProvider,
                $this->translationFieldMapping[$fieldName],
                $this->translationsCollectionProperty,
                $this->translationClass,
                $this->translationLocaleProperty,
                $this->translationMappingProperty,
                $property,
                $this->logger
            );
        }
    }
}
