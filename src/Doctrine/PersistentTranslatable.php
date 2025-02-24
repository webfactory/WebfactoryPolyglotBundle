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
use ReflectionNamedType;
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
     * The UninitializedPersistentTranslatable object instance used when this PersistentTranslatable instance is removed (ejected) from
     * an entity with managed translations. Needs to be kept as an object reference, so that multiple injection/ejection
     * cycles will use the same object instance. This is necessary to prevent Doctrine ORM change detection from treating
     * the value as changed every time.
     */
    private ?UninitializedPersistentTranslatable $valueForEjection = null;

    private LoggerInterface $logger;

    /**
     * @param UnitOfWork            $unitOfWork            The UoW managing the entity that contains this PersistentTranslatable
     * @param class-string          $class                 The class of the entity containing this PersistentTranslatable instance
     * @param object                $entity                The entity containing this PersistentTranslatable instance
     * @param string                $primaryLocale         The locale for which the translated value will be persisted in the "main" entity
     * @param DefaultLocaleProvider $defaultLocaleProvider DefaultLocaleProvider that provides the locale to use when no explicit locale is passed to e.g. translate()
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
        ?LoggerInterface $logger = null,
    ) {
        $this->oid = spl_object_id($entity);
        $this->logger = $logger ?? new NullLogger();

        $currentValue = $this->translatedProperty->getValue($this->entity);

        if ($currentValue instanceof UninitializedPersistentTranslatable) {
            $this->primaryValue = $currentValue->getPrimaryValue();
            $this->valueForEjection = $currentValue;
        } elseif ($currentValue instanceof Translatable) {
            $currentValue->copy($this);
        } else {
            $this->primaryValue = $currentValue;
        }
    }

    public function setPrimaryValue(mixed $value): void
    {
        $this->primaryValue = $value;
    }

    /**
     * @psalm-internal Webfactory\Bundle\PolyglotBundle
     */
    public function eject(): void
    {
        $value = $this->primaryValue;

        $type = $this->translatedProperty->getType();
        if ($type instanceof ReflectionNamedType && TranslatableInterface::class === $type->getName() && \is_string($value)) {
            if (!$this->valueForEjection || $this->valueForEjection->getPrimaryValue() !== $value) {
                $this->valueForEjection = new UninitializedPersistentTranslatable($value);
            }
            $value = $this->valueForEjection;
        }

        $this->translatedProperty->setValue($this->entity, $value);
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

    public function setTranslation(mixed $value, ?string $locale = null): void
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
    public function translate(?string $locale = null): mixed
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
            $message = \sprintf(
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
            $exceptionAsString .= \sprintf(
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
