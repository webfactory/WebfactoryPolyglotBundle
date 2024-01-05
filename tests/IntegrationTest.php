<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class IntegrationTest extends TestCase
{
    private ORMInfrastructure $infrastructure;

    private EntityManagerInterface $entityManager;

    private DefaultLocaleProvider $defaultLocaleProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->infrastructure = ORMInfrastructure::createOnlyFor([TestEntity::class, TestEntityTranslation::class]);
        $this->entityManager = $this->infrastructure->getEntityManager();
        $this->defaultLocaleProvider = new DefaultLocaleProvider('en_GB');

        $listener = new PolyglotListener(new AnnotationReader(), $this->defaultLocaleProvider);
        $this->entityManager->getEventManager()->addEventListener(
            ['postFlush', 'prePersist', 'preFlush', 'postLoad'],
            $listener
        );
    }

    public function testPersistingEntityWithPlainStringInTranslatableField(): void
    {
        $entity = new TestEntity('text');
        $this->infrastructure->import($entity);

        $entity = $this->clearAndRefetch($entity);
        self::assertEquals('text', $entity->getText());
    }

    public function testPersistingEntityWithTranslatableInstanceInTranslatableField(): void
    {
        $entity = new TestEntity(new Translatable('text'));
        $this->infrastructure->import($entity);

        $entity = $this->clearAndRefetch($entity);
        self::assertEquals('text', $entity->getText());
    }

    public function testGettingTranslationsFromManagedEntity(): void
    {
        // TranslatableTest::testReturnsMainValueAndTranslations checks this for the
        // plain Translatable instance. This test makes sure we can tuck the entity
        // into the DB, refetch it and things still work
        $entity = $this->createAndFetchTestEntity();
        self::assertEquals('text en_GB', $entity->getText()->translate('en_GB'));
        self::assertEquals('text de_DE', $entity->getText()->translate('de_DE'));
    }

    public function testOnceEntityHasBeenFetchedFromDbTheDefaultLocaleCanBeSwitched(): void
    {
        // When fetched from the DB, all Translatable fields are linked up with the DefaultLocaleProvider.
        // As long as the entity is unmanaged, this can only work when the DefaultLocaleProvider is passed
        // in - see TranslatableTest::testDefaultLocaleProviderCanProvideDefaultLocale

        $entity = $this->createAndFetchTestEntity();
        self::assertEquals('text en_GB', (string) $entity->getText());

        $this->defaultLocaleProvider->setDefaultLocale('de_DE');
        self::assertEquals('text de_DE', (string) $entity->getText());
    }

    public function testTranslationsAreImplicitlyPersistedForNewEntitiy(): void
    {
        $newEntity = $this->createTestEntity();

        $this->infrastructure->import($newEntity); // just import the "main" entity, which has no 'cascade="..."' configuration

        $newEntity = $this->clearAndRefetch($newEntity);
        self::assertEquals('text de_DE', $newEntity->getText()->translate('de_DE')); // translation is available, must have been persisted in the DB
    }

    public function testNewTranslationsAreImplicitlyPersistedForManagedEntitiy(): void
    {
        $managedEntity = $this->createAndFetchTestEntity();
        $managedEntity->getText()->setTranslation('text xx_XX', 'xx_XX');

        $this->entityManager->flush();

        $managedEntity = $this->clearAndRefetch($managedEntity);
        self::assertEquals('text xx_XX', $managedEntity->getText()->translate('xx_XX')); // Translation still there, must come from DB
    }

    public function testEntityConsideredCleanWhenNoTranslationWasChanged(): void
    {
        $entity = $this->createAndFetchTestEntity();

        $queryCount = \count($this->getQueries());

        /*
           Nothing was changed here, so the flush() should not need to write anything
           to the DB. To make this work, the PolyglotListener needs to remove all
           injected proxies before Doctrine performs the change detection. Afterwards,
           all proxies should be put back in place.
         */
        $this->infrastructure->getEntityManager()->flush();

        $queries = $this->getQueries();
        self::assertEquals($queryCount, \count($queries));

        self::assertInstanceOf(TranslatableInterface::class, $entity->getText());
    }

    private function createAndFetchTestEntity(): TestEntity
    {
        $entity = $this->createTestEntity();
        $this->infrastructure->import([$entity]);
        $this->entityManager->clear(); // work around https://github.com/webfactory/doctrine-orm-test-infrastructure/issues/23

        /** @var TestEntity $persistedEntity */
        $persistedEntity = $this->entityManager->find(TestEntity::class, $entity->getId());

        return $persistedEntity;
    }

    private function getQueries(): array
    {
        return $this->infrastructure->getQueries();
    }

    private function createTestEntity(): TestEntity
    {
        $translatable = new Translatable('text en_GB', 'en_GB');
        $translatable->setTranslation('text de_DE', 'de_DE');
        $translatable->setTranslation('text fr_FR', 'fr_FR');

        return new TestEntity($translatable);
    }

    private function clearAndRefetch(TestEntity $entity): TestEntity
    {
        $id = $entity->getId();
        $this->entityManager->clear(); // forget about all entities

        return $this->entityManager->find(TestEntity::class, $id); // Clean state, fetch entity from DB again
    }
}
