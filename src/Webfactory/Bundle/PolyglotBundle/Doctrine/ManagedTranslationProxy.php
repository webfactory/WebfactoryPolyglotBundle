<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use \Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use \ReflectionProperty;
use \ReflectionClass;
use Webfactory\Bundle\PolyglotBundle\Exception\TranslationException;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

/**
 * Eine TranslationProxy-Implementierung für eine Entität, die
 * bereits unter Verwaltung des EntityManagers steht.
 */
class ManagedTranslationProxy implements TranslatableInterface
{
    /**
     * @var array<string, array<string, object|null>> Cache für die Übersetzungen, indiziert nach Entity-OID und Locale.
     * Ist static, damit ihn sich verschiedene Proxies (für die gleiche Entität, aber
     * unterschiedliche Felder) teilen können.
     */
    static protected $_translations = array();

    /**
     * @var object Die Entität, in der sich dieser Proxy befindet (für die er Übersetzungen verwaltet).
     */
    protected $entity;

    /**
     * Der einzigartige Hash für die verwaltete Entität.
     * @var string
     */
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

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Sammelt neu hinzugefügte Übersetzungen, damit wir sie explizit speichern können, wenn ein
     * Objekt im ORM abgelegt wird.
     */
    private $addedTranslations = [];

    /**
     * @param object $entity
     * @param string $primaryLocale
     * @param DefaultLocaleProvider $defaultLocaleProvider
     * @param ReflectionProperty $translatedProperty
     * @param ReflectionProperty $translationCollection
     * @param ReflectionClass $translationClass
     * @param ReflectionProperty $localeField
     * @param ReflectionProperty $translationMapping
     * @param LoggerInterface $logger
     */
    public function __construct(
        $entity,
        $primaryLocale,
        DefaultLocaleProvider $defaultLocaleProvider,
        ReflectionProperty $translatedProperty,
        ReflectionProperty $translationCollection,
        ReflectionClass $translationClass,
        ReflectionProperty $localeField,
        ReflectionProperty $translationMapping,
        LoggerInterface $logger = null
    )
    {
        $this->entity = $entity;
        $this->oid = spl_object_hash($entity);
        $this->primaryLocale = $primaryLocale;
        $this->defaultLocaleProvider = $defaultLocaleProvider;
        $this->translatedProperty = $translatedProperty;
        $this->translationCollection = $translationCollection;
        $this->translationClass = $translationClass;
        $this->localeField = $localeField;
        $this->translationMapping = $translationMapping;
        $this->logger = ($logger == null) ? new NullLogger() : $logger;
    }

    public function setPrimaryValue($value)
    {
        $this->primaryValue = $value;
    }

    public function getPrimaryValue()
    {
        return $this->primaryValue;
    }

    /**
     * @param string $locale
     * @return object|null
     */
    protected function getTranslationEntity($locale)
    {
        if ($this->isTranslationCached($locale) === false) {
            $this->cacheTranslation($locale);
        }

        return $this->getCachedTranslation($locale);
    }

    /**
     * @param string $locale
     * @return object
     */
    protected function createTranslationEntity($locale)
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

    /**
     * @param string $value
     * @param string|null $locale
     */
    public function setTranslation($value, $locale = null)
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
     * @param string|null $locale
     * @return mixed|string
     * @throws TranslationException
     */
    public function translate($locale = null)
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
        } catch (\Exception $e) {
            $message = sprintf(
                'Cannot translate property %s::%s into locale %s',
                get_class($this->entity),
                $this->translatedProperty->getName(),
                $locale
            );
            throw new TranslationException($message, $e);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return (string)$this->translate();
        } catch (\Exception $e) {
            $this->logger->error($this->stringifyException($e));
            return '';
        }
    }

    public function preFlush(EntityManager $entityManager)
    {
        array_map(function ($entity) use ($entityManager) {
            $entityManager->persist($entity);
        }, $this->addedTranslations);
        $this->addedTranslations = [];
    }

    /**
     * @return string
     */
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
        $criteria = $this->createLocaleCriteria($locale);
        $translationsFilteredByLocale = $translationsInAllLanguages->matching($criteria);

        $translationInLocale = ($translationsFilteredByLocale->count() > 0) ? $translationsFilteredByLocale->first() : null;

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
     * @return object|null
     */
    protected function getCachedTranslation($locale)
    {
        return self::$_translations[$this->oid][$locale];
    }

    /**
     * @param \Exception $e
     * @return string
     */
    private function stringifyException(\Exception $e)
    {
        $exceptionAsString = '';
        while ($e !== null) {
            if (!empty($exceptionAsString)) {
                $exceptionAsString .= PHP_EOL . 'Previous exception: ' . PHP_EOL;
            }
            $exceptionAsString .= sprintf(
                "Exception '%s' with message '%s' in %s:%d\n%s",
                get_class($e),
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
