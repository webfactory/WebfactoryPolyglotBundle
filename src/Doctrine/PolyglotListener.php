<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

class PolyglotListener implements EventSubscriber
{
    public const CACHE_SALT = '$WebfactoryPolyglot';

    protected $reader;

    private $translatableClassMetadatasByClass = [];

    protected $translatedClasses = [];

    protected $defaultLocaleProvider;

    // private \WeakMap $entitiesWithTranslations;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

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

        // $this->entitiesWithTranslations = new \WeakMap();
    }

    public function getSubscribedEvents(): array
    {
        return [/*'postFlush',*/ 'prePersist', /*'preFlush',*/ 'onFlush', 'postLoad'];
    }

    public function postLoad(LifecycleEventArgs $event)
    {
        // Called when the entity has been hydrated
        $objectManager = $event->getObjectManager();
        $object = $event->getObject();
        // $this->entitiesWithTranslations[$object] = $object;

        foreach ($this->getTranslationMetadatas($object, $objectManager) as $tm) {
            $tm->injectPersistentTranslatables($object, $objectManager, $this->defaultLocaleProvider);
        }
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        // Called before a new entity is persisted for the first time
        $objectManager = $event->getObjectManager();
        $object = $event->getObject();
        // $this->entitiesWithTranslations[$object] = $object;

        foreach ($this->getTranslationMetadatas($object, $objectManager) as $tm) {
            $tm->injectPersistentTranslatables($object, $objectManager, $this->defaultLocaleProvider);
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $objectManager = $event->getObjectManager();
        $uow = $objectManager->getUnitOfWork();

        $scheduledEntityInsertions = $uow->getScheduledEntityInsertions();
        $scheduledEntityUpdates = $uow->getScheduledEntityUpdates();

        foreach (array_merge($scheduledEntityInsertions, $scheduledEntityUpdates) as $entity) {
            foreach ($this->getTranslationMetadatas($entity, $objectManager) as $tm) {
                $tm->onFlush($entity, $objectManager);
            }
        }
    }

    public function preFlush(PreFlushEventArgs $event)
    {
        return;
        $objectManager = $event->getObjectManager();

        // Called before changes are flushed out to the database - even before the change sets are computed
        foreach ($this->entitiesWithTranslations as $object) {
            foreach ($this->getTranslationMetadatas($object, $objectManager) as $tm) {
                $tm->preFlush($object, $objectManager);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event)
    {
        return;
        // The postFlush event occurs at the end of a flush operation.
        $objectManager = $event->getObjectManager();

        foreach ($this->entitiesWithTranslations as $object) {
            foreach ($this->getTranslationMetadatas($object, $objectManager) as $tm) {
                $tm->injectProxies($object, $this->defaultLocaleProvider);
            }
        }
    }

    /**
     * @return list<TranslatableClassMetadata>
     */
    private function getTranslationMetadatas(object $entity, EntityManager $em): array
    {
        $class = get_class($entity);

        if (!isset($this->translatableClassMetadatasByClass[$class])) {
            $this->translatableClassMetadatasByClass[$class] = [];
            $classMetadata = $em->getClassMetadata($class);

            foreach (array_merge([$classMetadata->name], $classMetadata->parentClasses) as $className) {
                if ($tm = $this->loadTranslationMetadataForClass($className, $em)) {
                    $this->translatableClassMetadatasByClass[$class][] = $tm;
                }
            }
        }

        return $this->translatableClassMetadatasByClass[$class];
    }

    private function loadTranslationMetadataForClass($className, EntityManager $em)
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
                if ($cached) { // evtl. ist im Cache gespeichert, dass die Klasse *nicht* übersetzt ist
                    /* @var $cached TranslatableClassMetadata */
                    $cached->wakeupReflection($reflectionService);
                    $cached->setLogger($this->logger);
                }

                return $cached;
            }
        }

        // Load/parse
        $meta = TranslatableClassMetadata::parseFromClass($className, $this->reader, $metadataFactory);
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
