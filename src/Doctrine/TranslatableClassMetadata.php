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
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Webfactory\Bundle\PolyglotBundle\Attribute;
use Webfactory\Bundle\PolyglotBundle\Exception\ShouldNotHappen;
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
     * @var ReflectionClass<object>|null
     */
    private ?ReflectionClass $translationClass = null;

    /**
     * Die Locale der Werte in der Haupt-Klasse.
     */
    private ?string $primaryLocale = null;

    private ?LoggerInterface $logger = null;

    /**
     * @param class-string<object> $class The FQCN for the entity class whose translatable fields are described by this
     *                                    TranslatableClassMetadata instance. If the class has base entity classes (or mapped
     *                                    superclasses), a separate instance of TranslatableClassMetadata will be used for
     *                                    their fields.
     */
    private function __construct(
        private readonly string $class
    ) {
    }

    /**
     * @param class-string<object> $class
     */
    public static function parseFromClass(string $class, ClassMetadataFactory $classMetadataFactory): ?self
    {
        /** @var ClassMetadata<object> $cm */
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
        if (null === $this->translationClass) {
            throw new ShouldNotHappen('translationClass cannot be null');
        }

        if (null === $this->primaryLocale) {
            throw new ShouldNotHappen('primaryLocale cannot be null');
        }

        if (null === $this->translationLocaleProperty) {
            throw new ShouldNotHappen('translationLocaleProperty cannot be null');
        }

        if (null === $this->translationMappingProperty) {
            throw new ShouldNotHappen('translationMappingProperty cannot be null');
        }

        if (null === $this->translationsCollectionProperty) {
            throw new ShouldNotHappen('translationsCollectionProperty cannot be null');
        }

        $sleep = new SerializedTranslatableClassMetadata();
        $sleep->class = $this->class;
        $sleep->primaryLocale = $this->primaryLocale;
        $sleep->translationClass = $this->translationClass->name;

        foreach ($this->translationFieldMapping as $fieldname => $property) {
            // @see https://github.com/phpstan/phpstan/issues/11334
            // @phpstan-ignore assign.propertyType
            $sleep->translationFieldMapping[$fieldname] = [$property->class, $property->name];
        }

        foreach ($this->translatedProperties as $fieldname => $property) {
            // @phpstan-ignore assign.propertyType
            $sleep->translatedProperties[$fieldname] = [$property->class, $property->name];
        }

        // @phpstan-ignore assign.propertyType
        $sleep->translationLocaleProperty = [$this->translationLocaleProperty->class, $this->translationLocaleProperty->name];
        // @phpstan-ignore assign.propertyType
        $sleep->translationsCollectionProperty = [$this->translationsCollectionProperty->class, $this->translationsCollectionProperty->name];
        // @phpstan-ignore assign.propertyType
        $sleep->translationMappingProperty = [$this->translationMappingProperty->class, $this->translationMappingProperty->name];

        return $sleep;
    }

    public static function wakeup(SerializedTranslatableClassMetadata $data, RuntimeReflectionService $reflectionService): self
    {
        $self = new self($data->class);
        $self->primaryLocale = $data->primaryLocale;
        $self->translationClass = $reflectionService->getClass($data->translationClass);

        foreach ($data->translationFieldMapping as $fieldname => $property) {
            $self->translationFieldMapping[$fieldname] = $reflectionService->getAccessibleProperty(...$property) ??
                throw new ShouldNotHappen("Cannot get reflection on {$property[0]}::{$property[1]}");
        }

        foreach ($data->translatedProperties as $fieldname => $property) {
            $self->translatedProperties[$fieldname] = $reflectionService->getAccessibleProperty(...$property) ??
                throw new ShouldNotHappen("Cannot get reflection on {$property[0]}::{$property[1]}");
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
            throw new RuntimeException(\sprintf('Unable to find the translations for %s. There should be a one-to-may collection holding the translation entities, and it should be marked with %s.', $class, Attribute\TranslationCollection::class));
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
            throw new RuntimeException(\sprintf('Class %s uses translations, so it needs to provide the primary locale with the %s attribute at the class level. This can either be at the class itself, or in one of its parent classes.', $class, Attribute\Locale::class));
        }
    }

    /**
     * @param ClassMetadata<object> $cm
     */
    private function findTranslatedProperties(ClassMetadata $cm, ClassMetadataFactory $classMetadataFactory): void
    {
        if (null === $this->translationClass) {
            return;
        }

        $reflectionService = $classMetadataFactory->getReflectionService();
        $translationClassMetadata = $classMetadataFactory->getMetadataFor($this->translationClass->getName());

        /* Iterate all properties of the class, not only those mapped by Doctrine */
        foreach ($cm->getReflectionClass()?->getProperties() ?? [] as $reflectionProperty) {
            $propertyName = $reflectionProperty->name;

            /*
                If the property is inherited from a parent class, and our parent entity class
                already contains that declaration, we need not include it.
            */
            $declaringClass = $reflectionProperty->getDeclaringClass()->name;
            if ($declaringClass !== $cm->name && [] !== $cm->parentClasses && is_a($cm->parentClasses[0], $declaringClass, true)) {
                continue;
            }

            $attributes = $reflectionProperty->getAttributes(Attribute\Translatable::class);

            if ([] === $attributes) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $this->translatedProperties[$propertyName] = $reflectionService->getAccessibleProperty($cm->name, $propertyName) ??
                throw new ShouldNotHappen("Cannot get reflection for {$cm->name}::{$propertyName}.");

            $translationFieldname = $attribute->getTranslationFieldname() ?? $propertyName;

            $this->translationFieldMapping[$propertyName] = $reflectionService->getAccessibleProperty($translationClassMetadata->name, $translationFieldname) ??
                throw new ShouldNotHappen("Cannot get reflection for {$translationClassMetadata->name}::{$translationFieldname}.");
        }
    }

    /**
     * @param ClassMetadata<object> $cm
     */
    private function findTranslationsCollection(ClassMetadata $cm, ClassMetadataFactory $classMetadataFactory): void
    {
        foreach ($cm->associationMappings as $fieldName => $mapping) {
            if (isset($mapping['declared'])) {
                // The association is inherited from a parent class
                continue;
            }

            $reflectionProperty = $cm->getReflectionProperty($fieldName);

            if (null !== $reflectionProperty && [] !== $reflectionProperty->getAttributes(Attribute\TranslationCollection::class)) {
                if (!$mapping instanceof InverseSideMapping) {
                    return;
                }

                $this->translationsCollectionProperty = $reflectionProperty;

                $translationEntityMetadata = $classMetadataFactory->getMetadataFor($mapping->targetEntity);
                $this->translationClass = $translationEntityMetadata->getReflectionClass();
                $this->translationMappingProperty = $translationEntityMetadata->getReflectionProperty($mapping->mappedBy);
                $this->parseTranslationsEntity($translationEntityMetadata);

                return;
            }
        }
    }

    /**
     * @param ClassMetadata<object> $cm
     */
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

    /**
     * @param ClassMetadata<object> $cm
     */
    private function parseTranslationsEntity(ClassMetadata $cm): void
    {
        foreach ($cm->fieldMappings as $fieldName => $mapping) {
            $reflectionProperty = $cm->getReflectionProperty($fieldName);

            if (null !== $reflectionProperty && [] !== $reflectionProperty->getAttributes(Attribute\Locale::class)) {
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
                $this->primaryLocale ?? new ShouldNotHappen('primaryLocale cannot be null.'),
                $defaultLocaleProvider,
                $this->translationFieldMapping[$fieldName],
                $this->translationsCollectionProperty ?? throw new ShouldNotHappen('primaryLocale cannot be null.'),
                $this->translationClass ?? throw new ShouldNotHappen('primaryLocale cannot be null.'),
                $this->translationLocaleProperty ?? throw new ShouldNotHappen('primaryLocale cannot be null.'),
                $this->translationMappingProperty ?? throw new ShouldNotHappen('primaryLocale cannot be null.'),
                $property,
                $this->logger
            );
            $persistentTranslatable->inject();
        }
    }

    /**
     * @return list<PersistentTranslatable<mixed>>
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
