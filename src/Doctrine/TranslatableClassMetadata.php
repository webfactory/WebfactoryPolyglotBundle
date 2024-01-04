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
use Doctrine\Persistence\Mapping\ReflectionService;
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
     * @var ReflectionProperty[]
     */
    private $translationFieldMapping = [];

    /**
     * Die Eigenschaften der Haupt-Klasse, die übersetzbar sind; indiziert nach Feldnamen.
     *
     * @var ReflectionProperty[]
     */
    private $translatedProperties = [];

    /**
     * Die Eigenschaft der Haupt-Klasse, die die Collection der Übersetzungen hält.
     *
     * @var ReflectionProperty
     */
    private $translationsCollectionProperty;

    /**
     * Die Eigenschaft der Übersetzungs-Klasse, die als many-to-one auf die Haupt-Klasse verweist.
     *
     * @var ReflectionProperty
     */
    private $translationMappingProperty;

    /**
     * Die Eigenschaft in der Übersetzungs-Klasse, die die Sprache einer Übersetzungsinstanz enhtält.
     *
     * @var ReflectionProperty
     */
    private $translationLocaleProperty;

    /**
     * @var ReflectionClass Die Übersetzungs-Klasse.
     */
    private $translationClass;

    /**
     * @var string Die Locale der Werte in der Haupt-Klasse.
     */
    private $primaryLocale;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public static function parseFromClassMetadata(ClassMetadataInfo $cm, Reader $reader): ?self
    {
        $tm = new static();
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

    public function prepareSleepInstance(): self
    {
        $sleep = clone $this;
        $sleep->logger = null;
        $sleep->translationClass = $this->translationClass->name;

        foreach ($sleep->translationFieldMapping as $fieldname => $property) {
            $sleep->translationFieldMapping[$fieldname] = [$property->class, $property->name];
        }

        foreach ($sleep->translatedProperties as $fieldname => $property) {
            $sleep->translatedProperties[$fieldname] = [$property->class, $property->name];
        }

        $sleep->translationLocaleProperty = [$sleep->translationLocaleProperty->class, $sleep->translationLocaleProperty->name];
        $sleep->translationsCollectionProperty = [$sleep->translationsCollectionProperty->class, $sleep->translationsCollectionProperty->name];
        $sleep->translationMappingProperty = [$sleep->translationMappingProperty->class, $sleep->translationMappingProperty->name];

        return $sleep;
    }

    public function wakeupReflection(ReflectionService $reflectionService)
    {
        $this->translationClass = $reflectionService->getClass($this->translationClass);

        foreach ($this->translationFieldMapping as $fieldname => $property) {
            $this->translationFieldMapping[$fieldname] = \call_user_func_array([$reflectionService, 'getAccessibleProperty'], $property);
        }

        foreach ($this->translatedProperties as $fieldname => $property) {
            $this->translatedProperties[$fieldname] = \call_user_func_array([$reflectionService, 'getAccessibleProperty'], $property);
        }

        $this->translationsCollectionProperty = \call_user_func_array([$reflectionService, 'getAccessibleProperty'], $this->translationsCollectionProperty);
        $this->translationMappingProperty = \call_user_func_array([$reflectionService, 'getAccessibleProperty'], $this->translationMappingProperty);
        $this->translationLocaleProperty = \call_user_func_array([$reflectionService, 'getAccessibleProperty'], $this->translationLocaleProperty);
    }

    private function assertNoAnnotationsArePresent()
    {
        return null === $this->translationClass
            && null === $this->translationLocaleProperty
            && null === $this->translationMappingProperty
            && 0 === \count($this->translatedProperties);
    }

    private function assertAnnotationsAreComplete()
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

    private function findTranslatedProperties(Reader $reader, ClassMetadata $classMetadata)
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

    private function findTranslationsCollection(Reader $reader, ClassMetadataInfo $classMetadata)
    {
        foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
            $annotation = $reader->getPropertyAnnotation(
                $property,
                Annotation\TranslationCollection::class
            );
            if (null !== $annotation) {
                $property->setAccessible(true);
                $this->translationsCollectionProperty = $property;
                $am = $classMetadata->getAssociationMapping($property->getName());
                $this->parseTranslationsEntity($reader, $am['targetEntity']);

                $translationMappingProperty = $this->translationClass->getProperty($am['mappedBy']);
                $translationMappingProperty->setAccessible(true);
                $this->translationMappingProperty = $translationMappingProperty;
                break;
            }
        }
    }

    private function findPrimaryLocale(Reader $reader, ClassMetadata $classMetadata)
    {
        $annotation = $reader->getClassAnnotation(
            $classMetadata->getReflectionClass(),
            Annotation\Locale::class
        );
        if (null !== $annotation) {
            $this->primaryLocale = $annotation->getPrimary();
        }
    }

    private function parseTranslationsEntity(Reader $reader, $class)
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

    public function preFlush(object $entity, EntityManager $entityManager)
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

    public function injectProxies(object $entity, DefaultLocaleProvider $defaultLocaleProvider)
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
    public function manageTranslations(object $entity, DefaultLocaleProvider $defaultLocaleProvider)
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
