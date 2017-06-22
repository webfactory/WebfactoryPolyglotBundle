<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Webfactory\Bundle\PolyglotBundle\Doctrine\ManagedTranslationProxy;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /** @var ORMInfrastructure */
    private $infrastructure;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var DefaultLocaleProvider */
    private $defaultLocaleProvider;

    protected function setUp()
    {
        parent::setUp();
        $this->infrastructure = ORMInfrastructure::createOnlyFor([TestEntity::class, TestEntityTranslation::class]);
        $this->entityManager = $this->infrastructure->getEntityManager();
        $this->defaultLocaleProvider = new DefaultLocaleProvider('en_GB');

        $listener = new PolyglotListener(new AnnotationReader(), $this->defaultLocaleProvider);
        $this->entityManager->getEventManager()->addEventSubscriber($listener);
    }

    public function testNewEntityCanBeUsedWithoutAddingTranslations()
    {
        $entity = new TestEntity('text');
        $this->infrastructure->import($entity);

        $entity = $this->clearAndRefetch($entity);
        $this->assertEquals('text', $entity->getText());
    }

    public function testOnceEntityHasBeenFetchedFromDbTheDefaultLocaleCanBeSwitched()
    {
        $entity = $this->createAndFetchTestEntity();
        $this->assertEquals('text en_GB', (string) $entity->getText());

        $this->defaultLocaleProvider->setDefaultLocale('de_DE');
        $this->assertEquals('text de_DE', (string) $entity->getText());
    }

    public function testGettingTranslationsFromManagedEntity()
    {
        $entity = $this->createAndFetchTestEntity();
        $this->assertEquals('text en_GB', $entity->getText()->translate('en_GB'));
        $this->assertEquals('text de_DE', $entity->getText()->translate('de_DE'));
    }

    public function testTranslationsNeedNotBeHandledExplicitlyWhenAddingNewEntity()
    {
        $test = $this->createTestEntity();
        $this->assertInstanceOf(Translatable::class, $test->getText());

        $this->infrastructure->import($test); // just import the "main" entity, which has no 'cascade="..."' configuration
        $this->assertInstanceOf(ManagedTranslationProxy::class, $test->getText()); // implementation detail?

        $test = $this->clearAndRefetch($test);
        $this->assertEquals('text de_DE', $test->getText()->translate('de_DE')); // translation is available, must have been persisted in the DB
    }

    public function testTranslationsNeedNotBeHandledExplicitlyWhenUpdatingEntity()
    {
        $entity = $this->createAndFetchTestEntity(); // Entity is not new, but has been fetched from the DB

        $entity->getText()->setTranslation('text xx_XX', 'xx_XX'); // Add a translation

        $this->entityManager->flush(); // flush changes
        $this->assertEquals('text xx_XX', $entity->getText()->translate('xx_XX')); // Translation is still there

        $entity = $this->clearAndRefetch($entity); // Completely discard and refetch
        $this->assertEquals('text xx_XX', $entity->getText()->translate('xx_XX')); // Translation still there, must come from DB
    }

    public function testProxiesAreRemovedBeforeFlushSoTheyDoNotCauseUpdates()
    {
        $entity = $this->createAndFetchTestEntity();

        $queryCount = count($this->getQueries());

        /*
           Nothing was changed here, so the flush() should not need to write anything
           to the DB. To make this work, the PolyglotListener needs to remove all
           injected proxies before Doctrine performs the change detection. Afterwards,
           all proxies should be put back in place.
         */
        $this->infrastructure->getEntityManager()->flush();

        $queries = $this->getQueries();
        $this->assertEquals($queryCount, count($queries));

        $this->assertInstanceOf(ManagedTranslationProxy::class, $entity->getText());
    }

    /**
     * @return TestEntity
     */
    private function createAndFetchTestEntity()
    {
        $entity = $this->createTestEntity();
        $this->infrastructure->import([$entity]);
        $this->entityManager->clear(); // work around https://github.com/webfactory/doctrine-orm-test-infrastructure/issues/23

        /** @var TestEntity $persistedEntity */
        $persistedEntity = $this->entityManager->find(TestEntity::class, $entity->getId());

        return $persistedEntity;
    }

    /**
     * @return \Webfactory\Doctrine\ORMTestInfrastructure\Query[]
     */
    private function getQueries()
    {
        return $this->infrastructure->getQueries();
    }

    private function createTestEntity()
    {
        $translatable = new Translatable('text en_GB', 'en_GB');
        $translatable->setTranslation('text de_DE', 'de_DE');
        $translatable->setTranslation('text fr_FR', 'fr_FR');

        return new TestEntity($translatable);
    }

    private function clearAndRefetch(TestEntity $entity)
    {
        $id = $entity->getId();
        $this->entityManager->clear(); // forget about all entities

        /** @var TestEntity $fetched */
        $fetched = $this->entityManager->find(TestEntity::class, $id); // Clean state, fetch entity from DB again

        return $fetched;
    }
}
