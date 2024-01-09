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
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

final class PolyglotListener implements EventSubscriber
{
    private const CACHE_SALT = '$WebfactoryPolyglot';

    /**
     * Field to cache TranslatableClassMetadata information for a class and all of its
     * parent classes, indexed by the leaf (child) class.
     *
     * @var array<class-string, list<TranslatableClassMetadata>>
     */
    private array $translatableClassMetadatasByClass = [];

    /**
     * Field to cache TranslatableClassMetadata information by class name.
     *
     * @var array<class-string, ?TranslatableClassMetadata>
     */
    private array $translatedClasses = [];

    public function __construct(
        private readonly Reader $annotationReader,
        private readonly DefaultLocaleProvider $defaultLocaleProvider,
        private readonly LoggerInterface $logger = null ?? new NullLogger(),
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            'prePersist',
            'postLoad',
            'onFlush',
        ];
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        // Called when the entity has been hydrated
        $objectManager = $event->getObjectManager();
        $object = $event->getObject();

        foreach ($this->getTranslationMetadatas($object, $objectManager) as $tm) {
            $tm->injectPersistentTranslatables($object, $objectManager, $this->defaultLocaleProvider);
        }
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        // Called before a new entity is persisted for the first time
        $objectManager = $event->getObjectManager();
        $object = $event->getObject();

        foreach ($this->getTranslationMetadatas($object, $objectManager) as $tm) {
            $tm->injectPersistentTranslatables($object, $objectManager, $this->defaultLocaleProvider);
        }
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();

        $restore = [];
        foreach (array_merge($uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates()) as $entity) {
            foreach ($this->getTranslationMetadatas($entity, $em) as $tm) {
                $restore = array_merge($restore, $tm->replaceTranslatablesWithPrimaryValues($entity));
            }
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(get_class($entity)), $entity);
            foreach ($restore as $callback) {
                $callback();
            }
        }
    }

    /**
     * @return list<TranslatableClassMetadata>
     */
    private function getTranslationMetadatas(object $entity, EntityManager $em): array
    {
        $class = $entity::class;

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

    private function loadTranslationMetadataForClass($className, EntityManager $em): ?TranslatableClassMetadata
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
        $meta = TranslatableClassMetadata::parseFromClass($className, $this->annotationReader, $metadataFactory);

        if (null !== $meta) {
            $meta->setLogger($this->logger);
            $this->translatedClasses[$className] = $meta;
        }

        // Save if cache driver available
        $cacheDriver?->save($className.self::CACHE_SALT, $meta?->sleep());

        return $meta;
    }
}
