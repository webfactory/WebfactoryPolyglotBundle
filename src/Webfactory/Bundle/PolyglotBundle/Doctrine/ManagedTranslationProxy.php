<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use \Doctrine\Common\Collections\Criteria;
use \ReflectionProperty;
use \ReflectionClass;

/**
 * Eine TranslationProxy-Implementierung für eine Entität, die
 * bereits unter Verwaltung des EntityManagers steht.
 */
class ManagedTranslationProxy implements TranslationProxy {

    /**
     * @var array Cache für die Übersetzungen, indiziert nach Entity-OID und Locale.
     * Ist static, damit ihn sich verschiedene Proxies (für die gleiche Entität, aber
     * unterschiedliche Felder) teilen können.
     */
    static protected $_translations = array();

    /** @var object Die Entität, in der sich dieser Proxy befindet (für die er Übersetzungen verwaltet). */
    protected $entity;
    protected $oid;

    /** @var string Sprache, die in der Entität direkt abgelegt ist ("originärer" Content) */
    protected $primaryLocale;

    /** @var mixed Der Wert in der primary locale (der Wert in der Entität, den der Proxy ersetzt hat) */
    protected $primaryValue = null;

    /** @var string Locale, in der dieser Proxy Werte zurückgibt, wenn keine andere Locale explizit gewünscht wird */
    protected $defaultLocale;

    /** @var ReflectionProperty ReflectionProperty für die Eigenschaft der Translation-Klasse, die den übersetzten Wert hält */
    protected $translatedProperty;

    /** @var ReflectionProperty ReflectionProperty für die Eigenschaft der Haupt-Klasse, in der die Übersetzungen als Doctrine Collection abgelegt sind. */
    protected $translationCollection;

    /** @var ReflectionClass ReflectionClass für die Klasse, die die Übersetzungen aufnimmt. */
    protected $translationClass;

    /** @var ReflectionProperty Das Feld in der Übersetzungs-Klasse, in dem die Locale einer Übersetzung abgelegt ist. */
    protected $localeField;

    /** @var ReflectionProperty Das Feld in der Übersetzungs-Klasse, in Many-to-one-Beziehung zur Entität abgelegt ist. */
    protected $translationMapping;

    public function __construct(
        $entity,
        $primaryLocale,
        $translationLocale, ReflectionProperty $translatedProperty,
        ReflectionProperty $translationCollection,
        ReflectionClass $translationClass,
        ReflectionProperty $localeField,
        ReflectionProperty $translationMapping
    ) {
        $this->entity = $entity;
        $this->oid = spl_object_hash($entity);
        $this->primaryLocale = $primaryLocale;
        $this->defaultLocale = $translationLocale;
        $this->translatedProperty = $translatedProperty;
        $this->translationCollection = $translationCollection;
        $this->translationClass = $translationClass;
        $this->localeField = $localeField;
        $this->translationMapping = $translationMapping;
    }

    public function setPrimaryValue($value) {
        $this->primaryValue = $value;
    }

    public function getPrimaryValue() {
        return $this->primaryValue;
    }

    protected function getTranslationEntity($locale) {
        if (!isset(self::$_translations[$this->oid][$locale])) {
            $criteria = Criteria::create()
                    ->where(Criteria::expr()->eq($this->localeField->getName(), $locale));

            /*
                The collection filtering API will issue a SQL query every time if the
                collection is not in memory; that is, it does not manage "partially initialized"
                collections.

                For this reason we cache the lookup results on our own (in-memory per-request)
                in a static member variable so they can be shared among all TranslationProxies.
            */
            if ($res = $this->translationCollection->getValue($this->entity)->matching($criteria)) {
                self::$_translations[$this->oid][$locale] = $res[0];
            } else
                self::$_translations[$this->oid][$locale] = null;
        }

        return self::$_translations[$this->oid][$locale];
    }

    protected function createTranslationEntity($locale) {
        $className = $this->translationClass->name;
        $localeField = $this->localeFieldname;
        $e = new $className;

        $this->localeField->setValue($e, $locale);

        $this->translationMapping->setValue($e, $this->entity);
        $this->translationCollection->getValue($this->entity)->add($e);

        self::$_translations[$this->oid][$locale] = $e;

        return $e;
    }

    public function setTranslation($value, $locale = null) {
        $locale = $locale ? : $this->defaultLocale;
        if ($locale == $this->primaryLocale) {
            $this->primaryValue = $value;
        } else {
            $e = $this->getTranslationEntity($locale);
            if (!$e)
                $e = $this->createTranslationEntity($locale);
            $this->translatedProperty->setValue($e, $value);
        }
    }

    public function translate($locale = null) {
        $locale = $locale ? : $this->defaultLocale;

        if ($locale == $this->primaryLocale) {
            return $this->primaryValue;
        }

        if ($e = $this->getTranslationEntity($locale)) {
            $translated = $this->translatedProperty->getValue($e);
            if (null !== $translated)
                return $translated;
        }

        return $this->primaryValue;
    }

    public function __toString() {
        return (string)$this->translate();
    }
}

