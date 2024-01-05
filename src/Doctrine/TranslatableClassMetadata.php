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
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
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
     * @var ReflectionClass Die Übersetzungs-Klasse.
     */
    private ?ReflectionClass $translationClass = null;

    /**
     * @var string Die Locale der Werte in der Haupt-Klasse.
     */
    private ?string $primaryLocale = null;

    private ?LoggerInterface $logger = null;

    public static function parseFromClassMetadata(ClassMetadataInfo $cm, Reader $reader): ?self
    {
        $tm = new self();
        $tm->findPrimaryLocale($reader, $cm);
        $tm->findTranslationsCollection($reader, $cm);
        $tm->findTranslatedProperties($reader, $cm);

        if ($tm->assertNoAnnotationsArePresent()) {
            return null;
        }
        $tm->assertAnnotationsAreComplete();

        return $tm;
    }

    public function setLogger(LoggerInterface $logger = null): void
    {
        $this->logger = $logger;
    }

    public function sleep(): SerializedTranslatableClassMetadata
    {
        $sleep = new SerializedTranslatableClassMetadata();
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
        $self = new self();
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
            && 0 === \count($this->translatedProperties);
    }

    private function assertAnnotationsAreComplete(): void
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
    }

    private function findTranslatedProperties(Reader $reader, ClassMetadata $classMetadata): void
    {
        if ($this->translationClass) {
            foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
                $annotation = $reader->getPropertyAnnotation(
                    $property,
                    Annotation\Translatable::class
                );
                if (null !== $annotation) {
                    $fieldname = $property->getName();
                    $property->setAccessible(true);

                    $this->translatedProperties[$fieldname] = $property;

                    $translationFieldname = $annotation->getTranslationFieldname() ?: $fieldname;
                    $translationField = $this->translationClass->getProperty($translationFieldname);
                    $translationField->setAccessible(true);
                    $this->translationFieldMapping[$fieldname] = $translationField;
                }
            }
        }
    }

    private function findTranslationsCollection(Reader $reader, ClassMetadataInfo $classMetadata): void
    {
        foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
            $annotation = $reader->getPropertyAnnotation(
                $property,
                Annotation\TranslationCollection::class
            );
            if (null !== $annotation) {
                $this->translationsCollectionProperty = $property;
                $am = $classMetadata->getAssociationMapping($property->getName());
                $this->parseTranslationsEntity($reader, $am['targetEntity']);

                $translationMappingProperty = $this->translationClass->getProperty($am['mappedBy']);
                $this->translationMappingProperty = $translationMappingProperty;
                break;
            }
        }
    }

    private function findPrimaryLocale(Reader $reader, ClassMetadata $classMetadata): void
    {
        $annotation = $reader->getClassAnnotation(
            $classMetadata->getReflectionClass(),
            Annotation\Locale::class
        );
        if (null !== $annotation) {
            $this->primaryLocale = $annotation->getPrimary();
        }
    }

    private function parseTranslationsEntity(Reader $reader, $class): void
    {
        $this->translationClass = new ReflectionClass($class);

        foreach ($this->translationClass->getProperties() as $property) {
            $annotation = $reader->getPropertyAnnotation(
                $property,
                Annotation\Locale::class
            );
            if (null !== $annotation) {
                $property->setAccessible(true);
                $this->translationLocaleProperty = $property;
            }
        }
    }

    public function preFlush(object $entity, EntityManager $entityManager): void
    {
        foreach ($this->translatedProperties as $property) {
            $proxy = $property->getValue($entity);

            if ($proxy instanceof PersistentTranslatable) {
                foreach ($proxy->getAndResetNewTranslations() as $translationEntity) {
                    $entityManager->persist($translationEntity);
                }
                $property->setValue($entity, $proxy->getPrimaryValue());
            }
        }
    }

    public function injectProxies(object $entity, DefaultLocaleProvider $defaultLocaleProvider): void
    {
        foreach ($this->translatedProperties as $fieldname => $property) {
            $proxy = $this->createProxy($entity, $fieldname, $defaultLocaleProvider);
            $proxy->setPrimaryValue($property->getValue($entity));
            $property->setValue($entity, $proxy);
        }
    }

    /**
     * For a given entity, find all @Translatable fields that contain new (not yet persisted)
     * Translatable objects and replace those with PersistentTranslatable.
     */
    public function manageTranslations(object $entity, DefaultLocaleProvider $defaultLocaleProvider): void
    {
        foreach ($this->translatedProperties as $fieldname => $property) {
            $translatableValue = $property->getValue($entity);

            if ($translatableValue instanceof Translatable) {
                $newProxy = $this->createProxy($entity, $fieldname, $defaultLocaleProvider);
                $translatableValue->copy($newProxy);
                $property->setValue($entity, $newProxy);
            }
        }
    }

    public function getTranslations(object $entity): Collection
    {
        return $this->translationsCollectionProperty->getValue($entity);
    }

    private function createProxy($entity, $fieldname, DefaultLocaleProvider $defaultLocaleProvider): PersistentTranslatable
    {
        return new PersistentTranslatable(
            $entity,
            $this->primaryLocale,
            $defaultLocaleProvider,
            $this->translationFieldMapping[$fieldname],
            $this->translationsCollectionProperty,
            $this->translationClass,
            $this->translationLocaleProperty,
            $this->translationMappingProperty,
            $this->logger
        );
    }
}
