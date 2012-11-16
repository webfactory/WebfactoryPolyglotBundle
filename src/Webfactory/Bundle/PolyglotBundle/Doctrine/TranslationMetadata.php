<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\ReflectionService;

class TranslationMetadata {

    /** @var \ReflectionProperty[] Ein Mapping von Feldnamen in der Hauptklasse auf die Felder in der Übersetzungs-Klasse, in denen die jeweilige Übersetzung liegt. */
    protected $translationFieldMapping = array();

    /** @var \ReflectionProperty[] Die Eigenschaften der Haupt-Klasse, die übersetzbar sind; indiziert nach Feldnamen. */
    protected $translatedProperties = array();

    /*+ @var \ReflectionProperty Die Eigenschaft der Haupt-Klasse, die die Collection der Übersetzungen hält. */
    protected $translationsCollectionProperty;

    /** @var \ReflectionProperty Die Eigenschaft der Übersetzungs-Klasse, die als many-to-one auf die Haupt-Klasse verweist. */
    protected $translationMappingProperty;

    /** @var \ReflectionProperty Die Eigenschaft in der Übersetzungs-Klasse, die die Sprache einer Übersetzungsinstanz enhtält. */
    protected $translationLocaleProperty;

    /** @var string Der Klassenname der Übersetzungs-Klasse. */
    protected $translationClass;

    /** @var string Die Locale der Werte in der Haupt-Klasse. */
    protected $primaryLocale;

    public static function parseFromClassMetadata(ClassMetadata $cm, Reader $reader) {
        $tm = new static();
        $tm->findPrimaryLocale($reader, $cm);
        $tm->findTranslationsCollection($reader, $cm);
        $tm->findTranslatedProperties($reader, $cm);

        return $tm->isComplete() ? $tm : null;
    }

    protected function __construct() { }

    protected function resurrect($property, ReflectionService $reflectionService) {
        return $reflectionService->getAccessibleProperty($property->class, $property->name);
    }

    public function wakeupReflection(ReflectionService $reflectionService) {
        foreach ($this->translationFieldMapping as $fieldname => $property) {
            $this->translationFieldMapping[$fieldname] = $this->resurrect($property, $reflectionService);
        }

        foreach ($this->translatedProperties as $fieldname => $property) {
            $this->translatedProperties[$fieldname] = $this->resurrect($property, $reflectionService);
        }

        $this->translationsCollectionProperty = $this->resurrect($this->translationsCollectionProperty, $reflectionService);
        $this->translationMappingProperty = $this->resurrect($this->translationMappingProperty, $reflectionService);
        $this->translationLocaleProperty = $this->resurrect($this->translationLocaleProperty, $reflectionService);
    }

    protected function isComplete() {
        return $this->translationClass && $this->translationLocaleProperty && $this->translationMappingProperty && $this->translatedProperties;
    }

    protected function findTranslatedProperties(Reader $reader, ClassMetadata $classMetadata) {
        if ($this->translationClass) {
            foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
                if ($annotation = $reader->getPropertyAnnotation($property, 'Webfactory\Bundle\PolyglotBundle\Annotation\Translatable')) {
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

    protected function findTranslationsCollection(Reader $reader, ClassMetadata $classMetadata) {
        foreach ($classMetadata->getReflectionClass()->getProperties() as $property) {
            if ($annotation = $reader->getPropertyAnnotation($property, 'Webfactory\Bundle\PolyglotBundle\Annotation\TranslationCollection')) {
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

    protected function findPrimaryLocale(Reader $reader, ClassMetadata $classMetadata) {
        if ($annotation = $reader->getClassAnnotation($classMetadata->getReflectionClass(), 'Webfactory\Bundle\PolyglotBundle\Annotation\Locale')) {
            $this->primaryLocale = $annotation->getPrimary();
        }
    }

    protected function parseTranslationsEntity(Reader $reader, $class) {
        $this->translationClass = new \ReflectionClass($class);

        foreach ($this->translationClass->getProperties() as $property) {
            if ($annotation = $reader->getPropertyAnnotation($property, 'Webfactory\Bundle\PolyglotBundle\Annotation\Locale')) {
                $property->setAccessible(true);
                $this->translationLocaleProperty = $property;
            }
        }
    }

    public function stripProxies($entity) {
        foreach ($this->translatedProperties as $property) {
            $proxy = $property->getValue($entity);
            if ($proxy instanceof ManagedTranslationProxy) {
                $property->setValue($entity, $proxy->getPrimaryValue());
            }
        }
    }

    public function injectProxies($entity, $defaultLocale) {
        foreach ($this->translatedProperties as $fieldname => $property) {
            $proxy = $this->createProxy($entity, $fieldname, $defaultLocale);
            $proxy->setPrimaryValue($property->getValue($entity));
            $property->setValue($entity, $proxy);
        }
    }

    public function replaceDetachedProxies($entity, $defaultLocale) {
        foreach ($this->translatedProperties as $fieldname => $property) {
            $proxy = $property->getValue($entity);

            if ($proxy instanceof \Webfactory\Bundle\PolyglotBundle\Translatable) {
                $newProxy = $this->createProxy($entity, $fieldname, $defaultLocale);
                $proxy->copy($newProxy);
                $property->setValue($entity, $newProxy);
            }
        }
    }

    public function getTranslations($entity) {
        return $this->translationsCollectionProperty->getValue($entity);
    }

    protected function createProxy($entity, $fieldname, $defaultLocale) {
        return new ManagedTranslationProxy(
            $entity,
            $this->primaryLocale,
            $defaultLocale, $this->translationFieldMapping[$fieldname],
            $this->translationsCollectionProperty,
            $this->translationClass,
            $this->translationLocaleProperty,
            $this->translationMappingProperty
        );
    }
}
