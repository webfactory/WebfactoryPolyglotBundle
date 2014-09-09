<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use \Doctrine\Common\Collections\Criteria;
use \ReflectionProperty;
use \ReflectionClass;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

/**
 * Eine TranslationProxy-Implementierung für eine Entität, die
 * bereits unter Verwaltung des EntityManagers steht.
 */
class ManagedTranslationProxy implements TranslatableInterface
{
    /**
     * @var array Cache für die Übersetzungen, indiziert nach Entity-OID und Locale.
     * Ist static, damit ihn sich verschiedene Proxies (für die gleiche Entität, aber
     * unterschiedliche Felder) teilen können.
     */
    static protected $_translations = array();

    /**
     * @var object Die Entität, in der sich dieser Proxy befindet (für die er Übersetzungen verwaltet).
     */
    protected $entity;

    protected $oid;

    /**
     * @var string Sprache, die in der Entität direkt abgelegt ist ("originärer" Content)
     */
    protected $primaryLocale;

    /**
     * @var mixed Der Wert in der primary locale (der Wert in der Entität, den der Proxy ersetzt hat)
     */
    protected $primaryValue = null;

    /**
     * @var DefaultLocaleProvider Provider, über den der Proxy die Locale erhält, in der Werte zurückgeben soll, wenn
     * keine andere Locale explizit gewünscht wird
     */
    protected $defaultLocaleProvider;

    /**
     * @var ReflectionProperty ReflectionProperty für die Eigenschaft der Translation-Klasse, die den übersetzten Wert
     * hält
     */
    protected $translatedProperty;

    /**
     * @var ReflectionProperty ReflectionProperty für die Eigenschaft der Haupt-Klasse, in der die Übersetzungen als
     * Doctrine Collection abgelegt sind.
     */
    protected $translationCollection;

    /**
     * @var ReflectionClass ReflectionClass für die Klasse, die die Übersetzungen aufnimmt.
     */
    protected $translationClass;

    /**
     * @var ReflectionProperty Das Feld in der Übersetzungs-Klasse, in dem die Locale einer Übersetzung abgelegt ist.
     */
    protected $localeField;

    /**
     * @var ReflectionProperty Das Feld in der Übersetzungs-Klasse, in Many-to-one-Beziehung zur Entität abgelegt ist.
     */
    protected $translationMapping;

    public function __construct(
        $entity,
        $primaryLocale,
        DefaultLocaleProvider $defaultLocaleProvider,
        ReflectionProperty $translatedProperty,
        ReflectionProperty $translationCollection,
        ReflectionClass $translationClass,
        ReflectionProperty $localeField,
        ReflectionProperty $translationMapping
    ) {
        $this->entity = $entity;
        $this->oid = spl_object_hash($entity);
        $this->primaryLocale = $primaryLocale;
        $this->defaultLocaleProvider = $defaultLocaleProvider;
        $this->translatedProperty = $translatedProperty;
        $this->translationCollection = $translationCollection;
        $this->translationClass = $translationClass;
        $this->localeField = $localeField;
        $this->translationMapping = $translationMapping;
    }

    public function setPrimaryValue($value)
    {
        $this->primaryValue = $value;
    }

    public function getPrimaryValue()
    {
        return $this->primaryValue;
    }

    protected function getTranslationEntity($locale)
    {
        if ($this->isTranslationCached($locale) === false) {
            $this->cacheTranslation($locale);
        }

        return $this->getCachedTranslation($locale);
    }

    protected function createTranslationEntity($locale)
    {
        $className = $this->translationClass->name;
        $entity = new $className;

        $this->localeField->setValue($entity, $locale);

        $this->translationMapping->setValue($entity, $this->entity);
        $this->translationCollection->getValue($this->entity)->add($entity);

        self::$_translations[$this->oid][$locale] = $entity;

        return $entity;
    }

    public function setTranslation($value, $locale = null)
    {
        $locale = $locale ? : $this->getDefaultLocale();
        if ($locale == $this->primaryLocale) {
            $this->primaryValue = $value;
        } else {
            $entity = $this->getTranslationEntity($locale);
            if (!$entity) {
                $entity = $this->createTranslationEntity($locale);
            }
            $this->translatedProperty->setValue($entity, $value);
        }
    }

    public function translate($locale = null)
    {
        $locale = $locale ? : $this->getDefaultLocale();

        if ($locale == $this->primaryLocale) {
            return $this->primaryValue;
        }

        if ($entity = $this->getTranslationEntity($locale)) {
            $translated = $this->translatedProperty->getValue($entity);
            if (null !== $translated) {
                return $translated;
            }
        }

        return $this->primaryValue;
    }

    public function __toString()
    {
        return (string)$this->translate();
    }

    protected function getDefaultLocale()
    {
        return $this->defaultLocaleProvider->getDefaultLocale();
    }

    /**
     * @param string $locale
     * @return bool
     */
    protected function isTranslationCached($locale)
    {
        return isset(self::$_translations[$this->oid][$locale]);
    }

    /**
     * The collection filtering API will issue a SQL query every time if the collection is not in memory; that is, it
     * does not manage "partially initialized" collections. For this reason we cache the lookup results on our own
     * (in-memory per-request) in a static member variable so they can be shared among all TranslationProxies.
     *
     * @param string $locale
     */
    protected function cacheTranslation($locale)
    {
        /* @var $translationsInAllLanguages \Doctrine\Common\Collections\Selectable */
        $translationsInAllLanguages = $this->translationCollection->getValue($this->entity);
        $translationsFilteredByLocale = $translationsInAllLanguages->matching($this->createLocaleCriteria($locale));
        $translationInLocale = $translationsFilteredByLocale->count() > 0 ? $translationsFilteredByLocale[0] : null;

        self::$_translations[$this->oid][$locale] = $translationInLocale;
    }

    /**
     * @param $locale
     * @return Criteria
     */
    protected function createLocaleCriteria($locale)
    {
        return Criteria::create()
                       ->where(
                           Criteria::expr()->eq($this->localeField->getName(), $locale)
                       );
    }

    /**
     * @param string $locale
     * @return mixed
     */
    protected function getCachedTranslation($locale)
    {
        return self::$_translations[$this->oid][$locale];
    }
}
