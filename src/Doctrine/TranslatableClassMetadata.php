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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\ObjectManager;
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
class TranslatableClassMetadata
{
    /**
     * The FQCN for the entity class whose translatable fields are described by this
     * TranslatableClassMetadata instance. If the class has base entity classes (or mapped
     * superclasses), a separate instance of TranslatableClassMetadata will be used for
     * their fields.
     *
     * @var string
     */
    private $class;

    /**
     * Ein Mapping von Feldnamen in der Hauptklasse auf die Felder in der
     * Übersetzungs-Klasse, in denen die jeweilige Übersetzung liegt.
     *
     * @var ReflectionProperty[]
     */
    protected $translationFieldMapping = [];

    /**
     * Die Eigenschaften der Haupt-Klasse, die übersetzbar sind; indiziert nach Feldnamen.
     *
     * @var ReflectionProperty[]
     */
    protected $translatedProperties = [];

    /**
     * Die Eigenschaft der Haupt-Klasse, die die Collection der Übersetzungen hält.
     *
     * @var ReflectionProperty
     */
    protected $translationsCollectionProperty;

    /**
     * Die Eigenschaft der Übersetzungs-Klasse, die als many-to-one auf die Haupt-Klasse verweist.
     *
     * @var ReflectionProperty
     */
    protected $translationMappingProperty;

    /**
     * Die Eigenschaft in der Übersetzungs-Klasse, die die Sprache einer Übersetzungsinstanz enhtält.
     *
     * @var ReflectionProperty
     */
    protected $translationLocaleProperty;

    /**
     * @var ReflectionClass Die Übersetzungs-Klasse.
     */
    protected $translationClass;

    /**
     * @var string Die Locale der Werte in der Haupt-Klasse.
     */
    protected $primaryLocale;

    /**
     * @var LoggerInterface|null
     */
    protected $logger = null;

    public static function parseFromClass(string $class, Reader $reader, ClassMetadataFactory $classMetadataFactory): ?self
    {
        $cm = $classMetadataFactory->getMetadataFor($class);

        $tm = new static($class);
        $tm->findPrimaryLocale($cm, $reader, $classMetadataFactory);
        $tm->findTranslationsCollection($cm, $reader, $classMetadataFactory);
        $tm->findTranslatedProperties($cm, $reader, $classMetadataFactory);

        if ($tm->assertNoAnnotationsArePresent()) {
            return null;
        }
        $tm->assertAnnotationsAreComplete($class);

        return $tm;
    }

    private function __construct(string $class)
    {
        $this->class = $class;
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

    protected function assertNoAnnotationsArePresent()
    {
        return null === $this->translationClass
            && null === $this->translationLocaleProperty
            && null === $this->translationMappingProperty
            && 0 === \count($this->translatedProperties)
            && null === $this->primaryLocale;
    }

    protected function assertAnnotationsAreComplete(string $class)
    {
        if (null === $this->translationClass) {
            throw new RuntimeException('The annotation with the translation class name is missing or incorrect, e.g. '.'@ORM\OneToMany(targetEntity="TestEntityTranslation", ...)');
        }

        if (null === $this->translationLocaleProperty) {
            throw new RuntimeException('The @Polyglot\Locale annotation at the language property of the translation class is missing or '.'incorrect');
        }

        if (null === $this->translationMappingProperty) {
            throw new RuntimeException('The attribute referenced in the mappedBy-Attribute of the @ORM\OneToMany(..., mappedBy="...") is '.'missing or incorrect');
        }

        if (0 === \count($this->translatedProperties)) {
            throw new RuntimeException('No translatable attributes annotated with @Polyglot\Translatable were found');
        }

        if (null === $this->primaryLocale) {
            throw new RuntimeException('A primary locale has to be set at the class level for ' . $class);
        }
    }

    protected function findTranslatedProperties(ClassMetadataInfo $cm, Reader $reader, ClassMetadataFactory $classMetadataFactory)
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

    protected function findTranslationsCollection(ClassMetadataInfo $cm, Reader $reader, ClassMetadataFactory $classMetadataFactory): void
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
                $this->translationsCollectionProperty = $cm->getReflectionProperty($fieldName);

                $translationEntityMetadata = $classMetadataFactory->getMetadataFor($mapping['targetEntity']);
                $this->translationClass = $translationEntityMetadata->getReflectionClass();
                $this->translationMappingProperty = $translationEntityMetadata->getReflectionProperty($mapping['mappedBy']);
                $this->parseTranslationsEntity($reader, $translationEntityMetadata);

                return;
            }
        }
    }

    protected function findPrimaryLocale(ClassMetadataInfo $cm, Reader $reader, ClassMetadataFactory $classMetadataFactory)
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

    public function onFlush($entity, EntityManager $entityManager): void
    {
        return;
//        $uow = $entityManager->getUnitOfWork();

        foreach ($this->translatedProperties as $property) {
            $value = $property->getValue($entity);

            if (!$value instanceof PersistentTranslatable) {
                continue;
            }

            $value->updateEntityChangeset();

//            foreach ($value->getAndResetNewTranslations() as $translationEntity) {
//                $entityManager->persist($translationEntity);
//                $uow->computeChangeSet($entityManager->getClassMetadata($this->translationClass->getName()), $translationEntity);
//            }

//            $changeSet =& $uow->getEntityChangeSet($entity);
//            $changeSet[$fieldName][1] = $value->getPrimaryValue();
        }
    }

//    public function preFlush($entity, EntityManager $entityManager)
//    {
//        foreach ($this->translatedProperties as $property) {
//            $proxy = $property->getValue($entity);
//
//            if ($proxy instanceof PersistentTranslatable) {
//                foreach ($proxy->getAndResetNewTranslations() as $translationEntity) {
//                    $entityManager->persist($translationEntity);
//                }
//                $property->setValue($entity, $proxy->getPrimaryValue());
//            }
//        }
//    }

//    public function injectProxies($entity, DefaultLocaleProvider $defaultLocaleProvider)
//    {
//        foreach ($this->translatedProperties as $fieldname => $property) {
//            $proxy = $this->createPersistentTranslatable($entity, $fieldname, $defaultLocaleProvider);
//            $proxy->setPrimaryValue($property->getValue($entity));
//            $property->setValue($entity, $proxy);
//        }
//    }

    /**
     * For a given entity, find all @Translatable fields that contain new (not yet persisted)
     * Translatable objects and replace those with PersistentTranslatable.
     */
    public function injectPersistentTranslatables(object $entity, EntityManager $entityManager, DefaultLocaleProvider $defaultLocaleProvider): void
    {
        $uow = $entityManager->getUnitOfWork();
//        $oid = spl_object_id($entity);

        foreach ($this->translatedProperties as $fieldName => $property) {
            $persistentTranslatable = new PersistentTranslatable(
                $uow,
                $this->class,
                $entity,
                $this->primaryLocale,
                $defaultLocaleProvider,
                $this->translationFieldMapping[$fieldName],
                $this->translationsCollectionProperty,
                $this->translationClass,
                $this->translationLocaleProperty,
                $this->translationMappingProperty,
                $this->logger
            );

            $value = $property->getValue($entity);

            if ($value instanceof Translatable) {
                $value->copy($persistentTranslatable);
            } else {
                $persistentTranslatable->setPrimaryValue($value);
            }

//            foreach ($persistentTranslatable->getAndResetNewTranslations() as $newTranslation) {
//                $entityManager->persist($newTranslation);
//            }

            $property->setValue($entity, $persistentTranslatable);

//            if ($uow->getOriginalEntityData($entity)) {
//                // Set $persistentTranslatable as the "original entity data", so Doctrine ORM
//                // change detection will not treat this new value as a relevant change
//                $uow->setOriginalEntityProperty($oid, $fieldName, $persistentTranslatable);
//            }
        }
    }

//    public function getTranslations($entity): Collection
//    {
//        return $this->translationsCollectionProperty->getValue($entity);
//    }

//    protected function createPersistentTranslatable(EntityManagerInterface $entityManager, $entity, $fieldname, DefaultLocaleProvider $defaultLocaleProvider): PersistentTranslatable
//    {
//        return new PersistentTranslatable(
//            $entityManager->getUnitOfWork(),
//            $this->class,
//            $entity,
//            $this->primaryLocale,
//            $defaultLocaleProvider,
//            $this->translationFieldMapping[$fieldname],
//            $this->translationsCollectionProperty,
//            $this->translationClass,
//            $this->translationLocaleProperty,
//            $this->translationMappingProperty,
//            $this->logger
//        );
//    }
}
