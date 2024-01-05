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
use Psr\Log\NullLogger;
use SplObjectStorage;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

final class PolyglotListener
{
    public const CACHE_SALT = '$WebfactoryPolyglot';

    private Reader $reader;

    /**
     * @var array<class-string, TranslatableClassMetadata>
     */
    private array $translatedClasses = [];

    private array $_proxiesStripped = [];
    private DefaultLocaleProvider $defaultLocaleProvider;

    private SplObjectStorage $entitiesWithTranslations;

    private LoggerInterface $logger;

    public function __construct(
        Reader $annotationReader,
        DefaultLocaleProvider $defaultLocaleProvider,
        LoggerInterface $logger = null
    ) {
        $this->reader = $annotationReader;
        $this->defaultLocaleProvider = $defaultLocaleProvider;
        $this->logger = $logger ?? new NullLogger();

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

        $className = $entity::class;

        return $this->getTranslationMetadata($className, $em);
    }

    private function getTranslationMetadata($className, EntityManager $em): ?TranslatableClassMetadata
    {
        // In memory cache
        if (isset($this->translatedClasses[$className])) {
            return $this->translatedClasses[$className];
        }

        $metadataFactory = $em->getMetadataFactory();
        $cacheDriver = $em->getConfiguration()->getMetadataCacheImpl();

        if ($cacheDriver) {
            if (($data = $cacheDriver->fetch($className.self::CACHE_SALT)) !== false) {
                if (null === $data) {
                    $this->translatedClasses[$className] = null;

                    return null;
                } else {
                    $wakeup = TranslatableClassMetadata::wakeup($data);
                    $wakeup->setLogger($this->logger);
                    $this->translatedClasses[$className] = $wakeup;

                    return $wakeup;
                }
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
        $cacheDriver?->save($className.self::CACHE_SALT, $meta?->sleep());

        return $meta;
    }
}
