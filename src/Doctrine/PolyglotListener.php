<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WeakReference;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

final class PolyglotListener
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

    /**
     * @var array<WeakReference>
     */
    private array $entitiesWithTranslatables = [];

    /**
     * @var list<PersistentTranslatable>
     */
    private array $ejectedTranslatables = [];

    public function __construct(
        private readonly DefaultLocaleProvider $defaultLocaleProvider,
        private readonly LoggerInterface $logger = null ?? new NullLogger(),
        private readonly RuntimeReflectionService $reflectionService = new RuntimeReflectionService(),
    ) {
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        // Called when the entity has been hydrated
        $this->injectPersistentTranslatables($event->getObjectManager(), $event->getObject());
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        // Called when a new entity is passed to persist() for the first time
        $this->injectPersistentTranslatables($event->getObjectManager(), $event->getObject());
    }

    private function injectPersistentTranslatables(EntityManager $entityManager, object $object): void
    {
        $hasTranslatables = false;

        foreach ($this->getTranslationMetadatas($object, $entityManager) as $tm) {
            $tm->injectNewPersistentTranslatables($object, $entityManager, $this->defaultLocaleProvider);
            $hasTranslatables = true;
        }

        if ($hasTranslatables) {
            $this->entitiesWithTranslatables[] = WeakReference::create($object);
        }
    }

    public function preFlush(PreFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();

        foreach ($this->entitiesWithTranslatables as $key => $weakRef) {
            $object = $weakRef->get();
            if (null === $object) {
                unset($this->entitiesWithTranslatables[$key]);
                continue;
            }

            foreach ($this->getTranslationMetadatas($object, $em) as $tm) {
                $this->ejectedTranslatables = array_merge($this->ejectedTranslatables, $tm->ejectPersistentTranslatables($object));
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        foreach ($this->ejectedTranslatables as $persistentTranslatable) {
            $persistentTranslatable->inject();
        }
        $this->ejectedTranslatables = [];
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
        $cache = $em->getConfiguration()->getMetadataCache();

        if ($cache?->hasItem($className.self::CACHE_SALT)) {
            $item = $cache->getItem($className.self::CACHE_SALT);
            $data = $item->get();
            if (null === $data) {
                $this->translatedClasses[$className] = null;

                return null;
            } else {
                $wakeup = TranslatableClassMetadata::wakeup($data, $this->reflectionService);
                $wakeup->setLogger($this->logger);
                $this->translatedClasses[$className] = $wakeup;

                return $wakeup;
            }
        }

        // Load/parse
        $meta = TranslatableClassMetadata::parseFromClass($className, $metadataFactory);

        if (null !== $meta) {
            $meta->setLogger($this->logger);
            $this->translatedClasses[$className] = $meta;
        }

        // Save if cache driver available
        if ($cache) {
            $item = $cache->getItem($className.self::CACHE_SALT);
            $item->set($meta?->sleep());
            $cache->save($item);
        }

        return $meta;
    }
}
