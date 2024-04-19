<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Webfactory\Bundle\PolyglotBundle\Attribute;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * For an entity class that contains #[Translatable] attributes, this class holds metadata
 * like which fields in the class are #[Translatable]s, which field holds the collection
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

    public static function parseFromClass(string $class, ClassMetadataFactory $classMetadataFactory): ?self
    {
        /** @var ClassMetadata $cm */
        $cm = $classMetadataFactory->getMetadataFor($class);

        $tm = new static($class);
        $tm->findPrimaryLocale($cm);
        $tm->findTranslationsCollection($cm, $classMetadataFactory);
        $tm->findTranslatedProperties($cm, $classMetadataFactory);

        if ($tm->isClassWithoutTranslations()) {
            return null;
        }
        $tm->assertAttributesAreComplete($class);

        return $tm;
    }

    public function setLogger(?LoggerInterface $logger = null): void
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

    public static function wakeup(SerializedTranslatableClassMetadata $data, RuntimeReflectionService $reflectionService): self
    {
        $self = new self($data->class);
        $self->primaryLocale = $data->primaryLocale;
        $self->translationClass = $reflectionService->getClass($data->translationClass);

        foreach ($data->translationFieldMapping as $fieldname => $property) {
            $self->translationFieldMapping[$fieldname] = $reflectionService->getAccessibleProperty(...$property);
        }

        foreach ($data->translatedProperties as $fieldname => $property) {
            $self->translatedProperties[$fieldname] = $reflectionService->getAccessibleProperty(...$property);
        }

        $self->translationsCollectionProperty = $reflectionService->getAccessibleProperty(...$data->translationsCollectionProperty);
        $self->translationMappingProperty = $reflectionService->getAccessibleProperty(...$data->translationMappingProperty);
        $self->translationLocaleProperty = $reflectionService->getAccessibleProperty(...$data->translationLocaleProperty);

        return $self;
    }

    private function isClassWithoutTranslations(): bool
    {
        return null === $this->translationClass
            && null === $this->translationLocaleProperty
            && null === $this->translationMappingProperty
            && 0 === \count($this->translatedProperties);
    }

    private function assertAttributesAreComplete(string $class): void
    {
        if (null === $this->translationClass) {
            throw new RuntimeException(sprintf('Unable to find the translations for %s. There should be a one-to-may collection holding the translation entities, and it should be marked with %s.', $class, Attribute\TranslationCollection::class));
        }

        if (null === $this->translationLocaleProperty) {
            throw new RuntimeException('The #[Polyglot\Locale] attribute at the language property of the translation class is missing or incorrect');
        }

        if (null === $this->translationMappingProperty) {
            throw new RuntimeException('The property referenced in the mappedBy-property of the #[ORM\OneToMany(..., mappedBy: "...")] is missing or incorrect');
        }

        if (0 === \count($this->translatedProperties)) {
            throw new RuntimeException('No translatable properties attributed with #[Polyglot\Translatable] were found');
        }

        if (null === $this->primaryLocale) {
            throw new RuntimeException(sprintf('Class %s uses translations, so it needs to provide the primary locale with the %s attribute at the class level. This can either be at the class itself, or in one of its parent classes.', $class, Attribute\Locale::class));
        }
    }

    private function findTranslatedProperties(ClassMetadata $cm, ClassMetadataFactory $classMetadataFactory): void
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

            $reflectionProperty = $cm->getReflectionProperty($fieldName);
            $attributes = $reflectionProperty->getAttributes(Attribute\Translatable::class);

            if (!$attributes) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $translationFieldname = $attribute->getTranslationFieldname() ?: $fieldName;
            $translationFieldReflectionProperty = $translationClassMetadata->getReflectionProperty($translationFieldname);

            $this->translatedProperties[$fieldName] = $reflectionProperty;
            $this->translationFieldMapping[$fieldName] = $translationFieldReflectionProperty;
        }
    }

    private function findTranslationsCollection(ClassMetadata $cm, ClassMetadataFactory $classMetadataFactory): void
    {
        foreach ($cm->associationMappings as $fieldName => $mapping) {
            if (isset($mapping['declared'])) {
                // The association is inherited from a parent class
                continue;
            }

            $reflectionProperty = $cm->getReflectionProperty($fieldName);

            if ($reflectionProperty->getAttributes(Attribute\TranslationCollection::class)) {
                $this->translationsCollectionProperty = $reflectionProperty;

                $translationEntityMetadata = $classMetadataFactory->getMetadataFor($mapping['targetEntity']);
                $this->translationClass = $translationEntityMetadata->getReflectionClass();
                $this->translationMappingProperty = $translationEntityMetadata->getReflectionProperty($mapping['mappedBy']);
                $this->parseTranslationsEntity($translationEntityMetadata);

                return;
            }
        }
    }

    private function findPrimaryLocale(ClassMetadata $cm): void
    {
        foreach (array_merge([$cm->name], $cm->parentClasses) as $class) {
            $reflectionClass = new ReflectionClass($class);

            foreach ($reflectionClass->getAttributes(Attribute\Locale::class) as $attribute) {
                $this->primaryLocale = $attribute->newInstance()->getPrimary();

                return;
            }
        }
    }

    private function parseTranslationsEntity(ClassMetadata $cm): void
    {
        foreach ($cm->fieldMappings as $fieldName => $mapping) {
            $reflectionProperty = $cm->getReflectionProperty($fieldName);

            if ($reflectionProperty->getAttributes(Attribute\Locale::class)) {
                $this->translationLocaleProperty = $reflectionProperty;

                return;
            }
        }
    }

    /**
     * For a given entity, find all @Translatable fields that contain new (not yet persisted)
     * Translatable objects and replace those with PersistentTranslatable.
     */
    public function injectNewPersistentTranslatables(object $entity, EntityManager $entityManager, DefaultLocaleProvider $defaultLocaleProvider): void
    {
        foreach ($this->translatedProperties as $fieldName => $property) {
            $persistentTranslatable = new PersistentTranslatable(
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
            $persistentTranslatable->inject();
        }
    }

    /**
     * @return list<PersistentTranslatable>
     */
    public function ejectPersistentTranslatables(object $entity): array
    {
        $ejectedTranslatables = [];

        foreach ($this->translatedProperties as $property) {
            $persistentTranslatable = $property->getValue($entity);
            \assert($persistentTranslatable instanceof PersistentTranslatable);
            $persistentTranslatable->eject();
            $ejectedTranslatables[] = $persistentTranslatable;
        }

        return $ejectedTranslatables;
    }
}
