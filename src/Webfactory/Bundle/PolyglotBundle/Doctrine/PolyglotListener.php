<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

class PolyglotListener implements EventSubscriber
{
    const CACHE_SALT = '$WebfactoryPolyglot';

    protected $reader;
    protected $translatedClasses = array();
    protected $_proxiesStripped = array();
    protected $defaultLocaleProvider;

    public function __construct(Reader $annotationReader, DefaultLocaleProvider $defaultLocaleProvider)
    {
        $this->reader = $annotationReader;
        $this->defaultLocaleProvider = $defaultLocaleProvider;
    }

    public function getSubscribedEvents()
    {
        return array(Events::postLoad, Events::preFlush, Events::prePersist, Events::postFlush);
    }

    public function postLoad(LifecycleEventArgs $event)
    {
        if ($tm = $this->getTranslationMetadataForLifecycleEvent($event)) {
            $tm->injectProxies($event->getEntity(), $this->defaultLocaleProvider);
        }
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        $em = $event->getEntityManager();

        if ($tm = $this->getTranslationMetadataForLifecycleEvent($event)) {
            $tm->replaceDetachedProxies($entity, $this->defaultLocaleProvider);
            /* Übersetzungen explizit persisten, -> kein "cascade" in der Klienten-Entitäts-Klasse notwendig */
            foreach ($tm->getTranslations($entity) as $translation) {
                $em->persist($translation);
            }
        }
    }

    public function preFlush(PreFlushEventArgs $event)
    {
        $em = $event->getEntityManager();
        /** @var $uow \Doctrine\ORM\UnitOfWork */
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() + $uow->getScheduledEntityUpdates() as $entity) {
            if ($tm = $this->getTranslationMetadata(get_class($entity), $em)) {
                $tm->stripProxies($entity);
                $this->_proxiesStripped[] = $entity;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event)
    {
        $em = $event->getEntityManager();

        while ($entity = array_shift($this->_proxiesStripped)) {
            $className = get_class($entity);
            $this->getTranslationMetadata($className, $em)->injectProxies($entity, $this->defaultLocaleProvider);
        }
    }

    protected function getTranslationMetadataForLifecycleEvent(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        $em = $event->getEntityManager();

        $className = get_class($entity);

        return $this->getTranslationMetadata($className, $em);
    }

    protected function getTranslationMetadata($className, EntityManager $em)
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

            if (($cached = $cacheDriver->fetch($className . self::CACHE_SALT)) !== false) {
                $this->translatedClasses[$className] = $cached;
                if ($cached) { // evtl. ist im Cache gespeichert, das die Klasse *nicht* übersetzt ist
                    $cached->wakeupReflection($reflectionService);
                }

                return $cached;
            }
        }

        // Load/parse
        $meta = TranslationMetadata::parseFromClassMetadata(
            $metadataFactory->getMetadataFor($className),
            $this->reader
        );
        if ($meta !== null) {
            $this->translatedClasses[$className] = $meta;
        }

        // Save if cache driver available
        if ($cacheDriver) {
            $cacheDriver->save($className . self::CACHE_SALT, $meta);
        }

        return $meta;
    }
}
