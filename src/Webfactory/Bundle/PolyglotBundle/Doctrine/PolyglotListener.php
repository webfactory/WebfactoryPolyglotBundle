<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;

class PolyglotListener implements EventSubscriber {

    const CACHE_SALT = '$WebfactoryPolyglot';

    protected $reader;
    protected $translatedClasses = array();
    protected $defaultLocale = 'en_GB';
    protected $_proxiesStripped = array();

    public function __construct(Reader $annotationReader) {
        $this->reader = $annotationReader;
    }

    public function setDefaultLocale($locale) {
        $this->defaultLocale = $locale;
    }

    public function getDefaultLocale() {
        return $this->defaultLocale;
    }

    public function getSubscribedEvents() {
        return array(Events::postLoad, Events::preFlush, Events::prePersist);
    }

    public function postLoad(LifecycleEventArgs $event) {
        if ($tm = $this->getTranslationMetadataForLifecycleEvent($event)) {
            $tm->injectProxies($event->getEntity(), $this->defaultLocale);
        }
    }

    public function prePersist(LifecycleEventArgs $event) {
        if ($tm = $this->getTranslationMetadataForLifecycleEvent($event)) {
            $tm->replaceDetachedProxies($event->getEntity(), $this->defaultLocale);
        }
    }

    public function preFlush(PreFlushEventArgs $event) {
        $em = $event->getEntityManager();
        /** @var $uow \Doctrine\ORM\UnitOfWork */ $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() + $uow->getScheduledEntityUpdates() as $entity) {
            if ($tm = $this->getTranslationMetadata(get_class($entity), $em)) {
                $tm->stripProxies($entity);
                $this->_proxiesStripped[] = $entity;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event) {
        $em = $event->getEntityManager();

        while ($entity = array_shift($this->_proxiesStripped)) {
            $className = get_class($entity);
            $this->getTranslationMetadata($className, $em)->injectProxies($entity, $this->defaultLocale);
        }
    }

    protected function getTranslationMetadataForLifecycleEvent(LifecycleEventArgs $event) {
        $entity = $event->getEntity();
        $em = $event->getEntityManager();

        $className = get_class($entity);

        return $this->getTranslationMetadata($className, $em);
    }

    protected function getTranslationMetadata($className, EntityManager $em) {
        // In memory cache
        if (isset($this->translatedClasses[$className]))
            return $this->translatedClasses[$className];

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
        if ($meta = TranslationMetadata::parseFromClassMetadata($metadataFactory->getMetadataFor($className), $this->reader, $reflectionService)) {
            $this->translatedClasses[$className] = $meta;
        }

        // Save if cache driver available
        if ($cacheDriver) {
            $cacheDriver->save($className . self::CACHE_SALT, $meta);
        }

        return $meta;
    }


}

