<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Psr\Log\LoggerInterface;
use SplObjectStorage;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

final class PolyglotListener
{
    const CACHE_SALT = '$WebfactoryPolyglot';

    private $reader;
    private $translatedClasses = [];
    private $_proxiesStripped = [];
    private $defaultLocaleProvider;

    /** @var SplObjectStorage */
    private $entitiesWithTranslations;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * PolyglotListener constructor.
     */
    public function __construct(
        Reader $annotationReader,
        DefaultLocaleProvider $defaultLocaleProvider,
        LoggerInterface $logger = null
    ) {
        $this->reader = $annotationReader;
        $this->defaultLocaleProvider = $defaultLocaleProvider;
        $this->logger = $logger;

        $this->entitiesWithTranslations = new SplObjectStorage();
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        // Called when the entity has been hydrated
        $entity = $event->getEntity();

        if ($tm = $this->getTranslationMetadataForLifecycleEvent($event)) {
            $this->entitiesWithTranslations->attach($entity, $tm);
            $tm->injectProxies($entity, $this->defaultLocaleProvider);
        }
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        // Called before a new entity is persisted for the first time
        $entity = $event->getEntity();

        if ($tm = $this->getTranslationMetadataForLifecycleEvent($event)) {
            $tm->manageTranslations($entity, $this->defaultLocaleProvider);
            $this->entitiesWithTranslations->attach($entity, $tm);
        }
    }

    public function preFlush(PreFlushEventArgs $event): void
    {
        $entityManager = $event->getEntityManager();
        // Called before changes are flushed out to the database - even before the change sets are computed
        foreach ($this->entitiesWithTranslations as $entity) {
            /** @var TranslatableClassMetadata $translationMetadata */
            $translationMetadata = $this->entitiesWithTranslations[$entity];
            $translationMetadata->preFlush($entity, $entityManager);
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        // The postFlush event occurs at the end of a flush operation.
        foreach ($this->entitiesWithTranslations as $entity) {
            $translationMetadata = $this->entitiesWithTranslations[$entity];
            $translationMetadata->injectProxies($entity, $this->defaultLocaleProvider);
        }
    }

    private function getTranslationMetadataForLifecycleEvent(LifecycleEventArgs $event): ?TranslatableClassMetadata
    {
        $entity = $event->getEntity();
        $em = $event->getEntityManager();

        $className = \get_class($entity);

        return $this->getTranslationMetadata($className, $em);
    }

    private function getTranslationMetadata($className, EntityManager $em): ?TranslatableClassMetadata
    {
        // In memory cache
        if (isset($this->translatedClasses[$className])) {
            return $this->translatedClasses[$className];
        }

        $metadataFactory = $em->getMetadataFactory();
        $reflectionService = $metadataFactory->getReflectionService();
        $cacheDriver = $em->getConfiguration()->getMetadataCacheImpl();

        // Cache driver available and in cache
        if ($cacheDriver) {
            if (($cached = $cacheDriver->fetch($className.self::CACHE_SALT)) !== false) {
                $this->translatedClasses[$className] = $cached;
                if ($cached) { // evtl. ist im Cache gespeichert, das die Klasse *nicht* übersetzt ist
                    /* @var $cached TranslatableClassMetadata */
                    $cached->wakeupReflection($reflectionService);
                    $cached->setLogger($this->logger);
                }

                return $cached;
            }
        }

        // Load/parse
        /* @var $metadataInfo ClassMetadataInfo */
        $metadataInfo = $metadataFactory->getMetadataFor($className);
        $meta = TranslatableClassMetadata::parseFromClassMetadata($metadataInfo, $this->reader);
        if (null !== $meta) {
            $meta->setLogger($this->logger);
            $this->translatedClasses[$className] = $meta;
        }

        // Save if cache driver available
        if ($cacheDriver) {
            $cacheDriver->save($className.self::CACHE_SALT, $meta ? $meta->prepareSleepInstance() : null);
        }

        return $meta;
    }
}
