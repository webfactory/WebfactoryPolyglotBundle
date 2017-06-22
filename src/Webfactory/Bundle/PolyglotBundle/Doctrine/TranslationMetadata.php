<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\ReflectionService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Psr\Log\LoggerInterface;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * Enthält für eine Klasse mit @Translatable-Annotations die Informationen darüber,
 * welche Felder der Klasse übersetzbar sind, wo die Übersetzungen gespeichert werden
 * etc.
 *
 * Es gibt eine Instanz dieser Klasse pro Entitätsklasse mit Übersetzungen.
 */
class TranslationMetadata
{
    /**
     * @var \ReflectionProperty[] Ein Mapping von Feldnamen in der Hauptklasse auf die Felder in der
     * Übersetzungs-Klasse, in denen die jeweilige Übersetzung liegt.
     */
    protected $translationFieldMapping = array();

    /**
     * @var \ReflectionProperty[] Die Eigenschaften der Haupt-Klasse, die übersetzbar sind; indiziert nach Feldnamen.
     */
    protected $translatedProperties = array();

    /**
     * @var \ReflectionProperty Die Eigenschaft der Haupt-Klasse, die die Collection der Übersetzungen hält.
     */
    protected $translationsCollectionProperty;

    /**
     * @var \ReflectionProperty Die Eigenschaft der Übersetzungs-Klasse, die als many-to-one auf die Haupt-Klasse
     * verweist.
     */
    protected $translationMappingProperty;

    /**
     * @var \ReflectionProperty Die Eigenschaft in der Übersetzungs-Klasse, die die Sprache einer Übersetzungsinstanz
     * enhtält.
     */
    protected $translationLocaleProperty;

    /**
     * @var \ReflectionClass Die Übersetzungs-Klasse.
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


    public static function parseFromClassMetadata(ClassMetadataInfo $cm, Reader $reader)
    {
        /* @var $tm TranslationMetadata */
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

    /**
     * @param LoggerInterface|null $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * serialize() checks if your class has a function with the magic name __sleep.
     * If so, that function is executed prior to any serialization.
     * It can clean up the object and is supposed to return an array with the names of all variables of that object that should be serialized.
     * If the method doesn't return anything then NULL is serialized and E_NOTICE is issued.
     * The intended use of __sleep is to commit pending data or perform similar cleanup tasks.
     * Also, the function is useful if you have very large objects which do not need to be saved completely.
     *
     * @return array|NULL
     * @link http://php.net/manual/en/language.oop5.magic.php#language.oop5.magic.sleep
     */
    function __sleep()
    {
        $properties = array_keys(get_object_vars($this));
        $notSerializableProperties = array('logger');
        return array_diff($properties, $notSerializableProperties);
    }

    protected function resurrect(\ReflectionProperty $property, ReflectionService $reflectionService)
    {
        return $reflectionService->getAccessibleProperty($property->class, $property->name);
    }

    public function wakeupReflection(ReflectionService $reflectionService)
    {
        foreach ($this->translationFieldMapping as $fieldname => $property) {
            $this->translationFieldMapping[$fieldname] = $this->resurrect($property, $reflectionService);
        }

        foreach ($this->translatedProperties as $fieldname => $property) {
            $this->translatedProperties[$fieldname] = $this->resurrect($property, $reflectionService);
        }

        $this->translationsCollectionProperty = $this->resurrect(
            $this->translationsCollectionProperty,
            $reflectionService
        );
        $this->translationMappingProperty = $this->resurrect($this->translationMappingProperty, $reflectionService);
        $this->translationLocaleProperty = $this->resurrect($this->translationLocaleProperty, $reflectionService);
    }

    protected function assertNoAnnotationsArePresent()
    {
        return $this->translationClass === null
            && $this->translationLocaleProperty === null
            && $this->translationMappingProperty === null
            && count($this->translatedProperties) === 0;
    }

    protected function assertAnnotationsAreComplete()
    {
        if ($this->translationClass === null) {
            throw new \RuntimeException(
                'The annotation with the translation class name is missing or incorrect, e.g. '
                . '@ORM\OneToMany(targetEntity="TestEntityTranslation", ...)'
            );
        }

        if ($this->translationLocaleProperty === null) {
            throw new \RuntimeException(
                'The @Polyglot\Locale annotation at the language property of the translation class is missing or '
                . 'incorrect'
            );
        }

        if ($this->translationMappingProperty === null) {
            throw new \RuntimeException(
                'The attribute referenced in the mappedBy-Attribute of the @ORM\OneToMany(..., mappedBy="...") is '
                . 'missing or incorrect'
            );
        }

        if (count($this->translatedProperties) === 0) {
            throw new \RuntimeException('No translatable attributes annotated with @Polyglot\Translatable were found');
        }
    }

    protected function findTranslatedProperties(Reader $reader, ClassMetadata $classMetadata)
    {
        if ($this->translationClass) {
            foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
                $annotation = $reader->getPropertyAnnotation(
                    $property,
                    'Webfactory\Bundle\PolyglotBundle\Annotation\Translatable'
                );
                if ($annotation !== null) {
                    $fieldname = $property->getName();
                    $property->setAccessible(true);

                    $this->translatedProperties[$fieldname] = $property;

                    $translationFieldname = $annotation->getTranslationFieldname() ? : $fieldname;
                    $translationField = $this->translationClass->getProperty($translationFieldname);
                    $translationField->setAccessible(true);
                    $this->translationFieldMapping[$fieldname] = $translationField;
                }
            }
        }
    }

    protected function findTranslationsCollection(Reader $reader, ClassMetadataInfo $classMetadata)
    {
        foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
            $annotation = $reader->getPropertyAnnotation(
                $property,
                'Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection'
            );
            if ($annotation !== null) {
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

    protected function findPrimaryLocale(Reader $reader, ClassMetadata $classMetadata)
    {
        $annotation = $reader->getClassAnnotation(
            $classMetadata->getReflectionClass(),
            'Webfactory\Bundle\PolyglotBundle\Annotation\Locale'
        );
        if ($annotation !== null) {
            $this->primaryLocale = $annotation->getPrimary();
        }
    }

    protected function parseTranslationsEntity(Reader $reader, $class)
    {
        $this->translationClass = new \ReflectionClass($class);

        foreach ($this->translationClass->getProperties() as $property) {
            $annotation = $reader->getPropertyAnnotation(
                $property,
                'Webfactory\Bundle\PolyglotBundle\Annotation\Locale'
            );
            if ($annotation !== null) {
                $property->setAccessible(true);
                $this->translationLocaleProperty = $property;
            }
        }
    }

    public function preFlush($entity, EntityManager $entityManager)
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

    public function injectProxies($entity, DefaultLocaleProvider $defaultLocaleProvider)
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
     *
     * @param object                $entity
     * @param DefaultLocaleProvider $defaultLocaleProvider
     */
    public function manageTranslations($entity, DefaultLocaleProvider $defaultLocaleProvider)
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

    public function getTranslations($entity)
    {
        $translations = $this->translationsCollectionProperty->getValue($entity);
        return $translations;
    }

    protected function createProxy($entity, $fieldname, DefaultLocaleProvider $defaultLocaleProvider)
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
