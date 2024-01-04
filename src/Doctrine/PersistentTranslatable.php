<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Collections\Criteria;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionProperty;
use Webfactory\Bundle\PolyglotBundle\Exception\TranslationException;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * Eine TranslationProxy-Implementierung für eine Entität, die
 * bereits unter Verwaltung des EntityManagers steht.
 */
final class PersistentTranslatable implements TranslatableInterface
{
    /**
     * Cache für die Übersetzungen, indiziert nach Entity-OID und Locale. Ist static, damit ihn sich verschiedene Proxies (für die gleiche Entität, aber unterschiedliche Felder) teilen können.
     *
     * @var array<string, array<string, object|null>>
     */
    private static $_translations = [];

    /**
     * Die Entität, in der sich dieser Proxy befindet (für die er Übersetzungen verwaltet).
     *
     * @var object
     */
    private $entity;

    /**
     * Der einzigartige Hash für die verwaltete Entität.
     *
     * @var string
     */
    private $oid;

    /**
     * @var string Sprache, die in der Entität direkt abgelegt ist ("originärer" Content)
     */
    private $primaryLocale;

    /**
     * @var mixed Der Wert in der primary locale (der Wert in der Entität, den der Proxy ersetzt hat)
     */
    private $primaryValue;

    /**
     * Provider, über den der Proxy die Locale erhält, in der Werte zurückgeben soll, wenn keine andere Locale explizit gewünscht wird.
     *
     * @var DefaultLocaleProvider
     */
    private $defaultLocaleProvider;

    /**
     * ReflectionProperty für die Eigenschaft der Translation-Klasse, die den übersetzten Wert hält.
     *
     * @var ReflectionProperty
     */
    private $translatedProperty;

    /**
     * ReflectionProperty für die Eigenschaft der Haupt-Klasse, in der die Übersetzungen als Doctrine Collection abgelegt sind.
     *
     * @var ReflectionProperty
     */
    private $translationCollection;

    /**
     * ReflectionClass für die Klasse, die die Übersetzungen aufnimmt.
     *
     * @var ReflectionClass
     */
    private $translationClass;

    /**
     * Das Feld in der Übersetzungs-Klasse, in dem die Locale einer Übersetzung abgelegt ist.
     *
     * @var ReflectionProperty
     */
    private $localeField;

    /**
     * Das Feld in der Übersetzungs-Klasse, in Many-to-one-Beziehung zur Entität abgelegt ist.
     *
     * @var ReflectionProperty
     */
    private $translationMapping;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Sammelt neu hinzugefügte Übersetzungen, damit wir sie explizit speichern können, wenn ein
     * Objekt im ORM abgelegt wird.
     */
    private $addedTranslations = [];

    public function __construct(
        object $entity,
        ?string $primaryLocale,
        DefaultLocaleProvider $defaultLocaleProvider,
        ReflectionProperty $translatedProperty,
        ReflectionProperty $translationCollection,
        ReflectionClass $translationClass,
        ReflectionProperty $localeField,
        ReflectionProperty $translationMapping,
        LoggerInterface $logger = null
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
        $this->logger = (null == $logger) ? new NullLogger() : $logger;
    }

    public function setPrimaryValue($value)
    {
        $this->primaryValue = $value;
    }

    /**
     * @return mixed|null
     */
    public function getPrimaryValue()
    {
        return $this->primaryValue;
    }

    /**
     * @param string $locale
     *
     * @return object|null
     */
    private function getTranslationEntity($locale)
    {
        if (false === $this->isTranslationCached($locale)) {
            $this->cacheTranslation($locale);
        }

        return $this->getCachedTranslation($locale);
    }

    /**
     * @param string $locale
     *
     * @return object
     */
    private function createTranslationEntity($locale)
    {
        $className = $this->translationClass->name;
        $entity = new $className();

        $this->localeField->setValue($entity, $locale);

        $this->translationMapping->setValue($entity, $this->entity);
        $this->translationCollection->getValue($this->entity)->add($entity);

        self::$_translations[$this->oid][$locale] = $entity;
        $this->addedTranslations[] = $entity;

        return $entity;
    }

    public function setTranslation($value, string $locale = null)
    {
        $locale = $locale ?: $this->getDefaultLocale();
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

    /**
     * @throws TranslationException
     */
    public function translate(string $locale = null)
    {
        $locale = $locale ?: $this->getDefaultLocale();
        try {
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
        } catch (Exception $e) {
            $message = sprintf(
                'Cannot translate property %s::%s into locale %s',
                \get_class($this->entity),
                $this->translatedProperty->getName(),
                $locale
            );
            throw new TranslationException($message, $e);
        }
    }

    public function isTranslatedInto(string $locale)
    {
        if ($locale === $this->primaryLocale) {
            return !empty($this->primaryValue);
        }

        $entity = $this->getTranslationEntity($locale);

        return $entity && null !== $this->translatedProperty->getValue($entity);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return (string) $this->translate();
        } catch (Exception $e) {
            $this->logger->error($this->stringifyException($e));

            return '';
        }
    }

    /**
     * Clears the list of newly added translation entities, returning the old value.
     *
     * @return object[] The list of new entites holding translations (exact class depends on the entity containing this proxy), before being reset.
     */
    public function getAndResetNewTranslations()
    {
        $newTranslations = $this->addedTranslations;
        $this->addedTranslations = [];

        return $newTranslations;
    }

    /**
     * @return string
     */
    private function getDefaultLocale()
    {
        return $this->defaultLocaleProvider->getDefaultLocale();
    }

    /**
     * @param string $locale
     *
     * @return bool
     */
    private function isTranslationCached($locale)
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
    private function cacheTranslation($locale)
    {
        /* @var $translationsInAllLanguages \Doctrine\Common\Collections\Selectable */
        $translationsInAllLanguages = $this->translationCollection->getValue($this->entity);
        $criteria = $this->createLocaleCriteria($locale);
        $translationsFilteredByLocale = $translationsInAllLanguages->matching($criteria);

        $translationInLocale = ($translationsFilteredByLocale->count() > 0) ? $translationsFilteredByLocale->first() : null;

        self::$_translations[$this->oid][$locale] = $translationInLocale;
    }

    /**
     * @return Criteria
     */
    private function createLocaleCriteria($locale)
    {
        return Criteria::create()
            ->where(
                Criteria::expr()->eq($this->localeField->getName(), $locale)
            );
    }

    /**
     * @param string $locale
     *
     * @return object|null
     */
    private function getCachedTranslation($locale)
    {
        return self::$_translations[$this->oid][$locale];
    }

    /**
     * @return string
     */
    private function stringifyException(Exception $e)
    {
        $exceptionAsString = '';
        while (null !== $e) {
            if (!empty($exceptionAsString)) {
                $exceptionAsString .= \PHP_EOL.'Previous exception: '.\PHP_EOL;
            }
            $exceptionAsString .= sprintf(
                "Exception '%s' with message '%s' in %s:%d\n%s",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            $e = $e->getPrevious();
        }

        return $exceptionAsString;
    }
}
