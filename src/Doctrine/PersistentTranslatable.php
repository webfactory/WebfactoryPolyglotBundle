<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\UnitOfWork;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
use Webfactory\Bundle\PolyglotBundle\Exception\TranslationException;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * This class implements `TranslatableInterface` for entities that are managed by
 * the entity manager. PolyglotListener will replace `Translatable` instances with
 * instances of this class as soon as a new entity is passed to EntityManager::persist().
 */
final class PersistentTranslatable implements TranslatableInterface
{
    /**
     * Cache to speed up accessing translated values, indexed by entity class, entity OID and locale.
     * This is static so that it can be shared by multiple PersistentTranslatable instances that
     * operate for the same entity instance, but different fields.
     *
     * @var array<class-string, array<int, array<string, object|null>>>
     */
    private static array $_translations = [];

    /**
     * Object id for $this->entity.
     */
    private int $oid;

    /**
     * The "primary" (untranslated) value for the property covered by this Translatable.
     */
    private mixed $primaryValue;

    /**
     * Whether original entity data was loaded by the ORM.
     */
    private bool $hasOriginalEntityData;

    /**
     * The original field value loaded by the ORM.
     */
    private mixed $originalEntityData;

    private LoggerInterface $logger;

    /**
     * @param UnitOfWork            $unitOfWork            The UoW managing the entity that contains this PersistentTranslatable
     * @param class-string          $class                 The class of the entity containing this PersistentTranslatable instance
     * @param object                $entity                The entity containing this PersistentTranslatable instance
     * @param string                $primaryLocale         The locale for which the translated value will be persisted in the "main" entity
     * @param DefaultLocaleProvider $defaultLocaleProvider DefaultLocaleProvider that provides the locale to use when no explicit locale is passed to e. g. translate()
     * @param ReflectionProperty    $translationProperty   ReflectionProperty pointing to the field in the translations class that holds the translated value to use
     * @param ReflectionProperty    $translationCollection ReflectionProperty pointing to the collection in the main class that holds translation instances
     * @param ReflectionClass       $translationClass      ReflectionClass for the class holding translated values
     * @param ReflectionProperty    $localeField           ReflectionProperty pointing to the field in the translations class that holds a translation's locale
     * @param ReflectionProperty    $translationMapping    ReflectionProperty pointing to the field in the translations class that refers back to the main entity (the owning side of the one-to-many translations collection).
     * @param ReflectionProperty    $translatedProperty    ReflectionProperty pointing to the field in the main entity where this PersistentTranslatable instance will be used
     */
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly string $class,
        private readonly object $entity,
        private readonly string $primaryLocale,
        private readonly DefaultLocaleProvider $defaultLocaleProvider,
        private readonly ReflectionProperty $translationProperty,
        private readonly ReflectionProperty $translationCollection,
        private readonly ReflectionClass $translationClass,
        private readonly ReflectionProperty $localeField,
        private readonly ReflectionProperty $translationMapping,
        private readonly ReflectionProperty $translatedProperty,
        LoggerInterface $logger = null,
    ) {
        $this->oid = spl_object_id($entity);
        $this->logger = $logger ?? new NullLogger();

        $data = $this->unitOfWork->getOriginalEntityData($entity);

        if ($data) {
            $fieldName = $this->translatedProperty->getName();
            $this->hasOriginalEntityData = true;
            $this->originalEntityData = $data[$fieldName];

            // Set $this as the "original entity data", so Doctrine ORM
            // change detection will not treat this new value as a relevant change
            $this->unitOfWork->setOriginalEntityProperty($this->oid, $fieldName, $this);
        } else {
            $this->hasOriginalEntityData = false;
        }

        $currentValue = $this->translatedProperty->getValue($this->entity);

        if ($currentValue instanceof Translatable) {
            $currentValue->copy($this);
        } else {
            $this->primaryValue = $currentValue;
        }

        $this->inject();
    }

    public function setPrimaryValue(mixed $value): void
    {
        $this->primaryValue = $value;

        if (!$this->hasOriginalEntityData) {
            return;
        }

        $fieldName = $this->translatedProperty->getName();

        if ($value !== $this->originalEntityData) {
            // Reset original entity data for the property where this PersistentTranslatable instance
            // is being used. This way, on changeset computation in the ORM, the original data will mismatch
            // the current value (which is $this object!). This will make $this->entity show up in the list
            // of entity updates in the UoW.
            $this->unitOfWork->setOriginalEntityProperty($this->oid, $fieldName, $this->originalEntityData);
        } else {
            $this->unitOfWork->setOriginalEntityProperty($this->oid, $fieldName, $this);
        }
    }

    /**
     * @psalm-internal Webfactory\Bundle\PolyglotBundle
     */
    public function eject(): void
    {
        $this->translatedProperty->setValue($this->entity, $this->primaryValue);
    }

    /**
     * @psalm-internal Webfactory\Bundle\PolyglotBundle
     */
    public function inject(): void
    {
        $this->translatedProperty->setValue($this->entity, $this);
    }

    private function getTranslationEntity(string $locale): ?object
    {
        if (!$this->isTranslationCached($locale)) {
            $this->cacheTranslation($locale);
        }

        return $this->getCachedTranslation($locale);
    }

    private function createTranslationEntity(string $locale): object
    {
        $className = $this->translationClass->name;
        $entity = new $className();

        $this->localeField->setValue($entity, $locale);

        $this->translationMapping->setValue($entity, $this->entity);
        $this->translationCollection->getValue($this->entity)->add($entity);

        self::$_translations[$this->class][$this->oid][$locale] = $entity;
        $this->unitOfWork->persist($entity);

        return $entity;
    }

    public function setTranslation(mixed $value, string $locale = null): void
    {
        $locale = $locale ?: $this->getDefaultLocale();
        if ($locale === $this->primaryLocale) {
            $this->setPrimaryValue($value);
        } else {
            $entity = $this->getTranslationEntity($locale);
            if (!$entity) {
                $entity = $this->createTranslationEntity($locale);
            }
            $this->translationProperty->setValue($entity, $value);
        }
    }

    /**
     * @throws TranslationException
     */
    public function translate(string $locale = null): mixed
    {
        $locale = $locale ?: $this->getDefaultLocale();
        try {
            if ($locale === $this->primaryLocale) {
                return $this->primaryValue;
            }

            if ($entity = $this->getTranslationEntity($locale)) {
                $translated = $this->translationProperty->getValue($entity);
                if (null !== $translated) {
                    return $translated;
                }
            }

            return $this->primaryValue;
        } catch (Exception $e) {
            $message = sprintf(
                'Cannot translate property %s::%s into locale %s',
                \get_class($this->entity),
                $this->translationProperty->getName(),
                $locale
            );
            throw new TranslationException($message, $e);
        }
    }

    public function isTranslatedInto(string $locale): bool
    {
        if ($locale === $this->primaryLocale) {
            return !empty($this->primaryValue);
        }

        $entity = $this->getTranslationEntity($locale);

        return $entity && null !== $this->translationProperty->getValue($entity);
    }

    public function __toString(): string
    {
        try {
            return (string) $this->translate();
        } catch (Throwable $e) {
            $this->logger->error($this->stringifyException($e));

            return '';
        }
    }

    private function getDefaultLocale(): string
    {
        return $this->defaultLocaleProvider->getDefaultLocale();
    }

    private function isTranslationCached(string $locale): bool
    {
        return isset(self::$_translations[$this->class][$this->oid][$locale]);
    }

    /**
     * The collection filtering API will issue a SQL query every time if the collection is not in memory; that is, it
     * does not manage "partially initialized" collections. For this reason, we cache the lookup results on our own
     * (in-memory per-request) in a static member variable, so they can be shared among all TranslationProxies.
     */
    private function cacheTranslation(string $locale): void
    {
        /** @var $translationsInAllLanguages Selectable */
        $translationsInAllLanguages = $this->translationCollection->getValue($this->entity);
        $criteria = $this->createLocaleCriteria($locale);
        $translationsFilteredByLocale = $translationsInAllLanguages->matching($criteria);

        $translationInLocale = ($translationsFilteredByLocale->count() > 0) ? $translationsFilteredByLocale->first() : null;

        self::$_translations[$this->class][$this->oid][$locale] = $translationInLocale;
    }

    private function createLocaleCriteria(string $locale): Criteria
    {
        return Criteria::create()
            ->where(
                Criteria::expr()->eq($this->localeField->getName(), $locale)
            );
    }

    private function getCachedTranslation(string $locale): ?object
    {
        return self::$_translations[$this->class][$this->oid][$locale];
    }

    private function stringifyException(Throwable $e): string
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
