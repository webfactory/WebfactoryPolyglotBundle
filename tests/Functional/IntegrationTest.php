<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

class IntegrationTest extends BaseFunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOrmInfrastructure([TestEntity::class, TestEntityTranslation::class]);
    }

    public function testPersistingEntityWithPlainStringInTranslatableField()
    {
        $entity = new TestEntity('text');
        $this->infrastructure->import($entity);

        $entity = $this->fetch($entity);
        self::assertEquals('text', $entity->getText());
    }

    public function testPersistingEntityWithTranslatableInstanceInTranslatableField()
    {
        $entity = new TestEntity(new Translatable('text'));
        $this->infrastructure->import($entity);

        $entity = $this->fetch($entity);
        self::assertEquals('text', $entity->getText());
    }

    public function testGettingTranslationsFromManagedEntity()
    {
        // TranslatableTest::testReturnsMainValueAndTranslations checks this for the
        // plain Translatable instance. This test makes sure we can tuck the entity
        // into the DB, refetch it and things still work
        $entity = $this->createAndFetchTestEntity();
        self::assertEquals('text en_GB', $entity->getText()->translate('en_GB'));
        self::assertEquals('text de_DE', $entity->getText()->translate('de_DE'));
    }

    public function testOnceEntityHasBeenFetchedFromDbTheDefaultLocaleCanBeSwitched()
    {
        // When fetched from the DB, all Translatable fields are linked up with the DefaultLocaleProvider.
        // As long as the entity is unmanaged, this can only work when the DefaultLocaleProvider is passed
        // in - see TranslatableTest::testDefaultLocaleProviderCanProvideDefaultLocale

        $entity = $this->createAndFetchTestEntity();
        self::assertEquals('text en_GB', (string) $entity->getText());

        $this->defaultLocaleProvider->setDefaultLocale('de_DE');
        self::assertEquals('text de_DE', (string) $entity->getText());
    }

    public function testTranslationsAreImplicitlyPersistedForNewEntitiy()
    {
        $newEntity = $this->createTestEntity();

        $this->infrastructure->import($newEntity); // just import the "main" entity, which has no 'cascade="..."' configuration

        $newEntity = $this->fetch($newEntity);
        self::assertEquals('text de_DE', $newEntity->getText()->translate('de_DE')); // translation is available, must have been persisted in the DB
    }

    public function testNewTranslationsAreImplicitlyPersistedForManagedEntitiy()
    {
        $managedEntity = $this->createAndFetchTestEntity();
        $managedEntity->getText()->setTranslation('text xx_XX', 'xx_XX');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $managedEntity = $this->fetch($managedEntity);
        self::assertEquals('text xx_XX', $managedEntity->getText()->translate('xx_XX')); // Translation still there, must come from DB
    }

    public function testEntityConsideredCleanWhenNoTranslationWasChanged()
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

    /**
     * @return TestEntity
     */
    private function createAndFetchTestEntity()
    {
        $entity = $this->createTestEntity();
        $this->infrastructure->import($entity);

        return $this->fetch($entity);
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

    private function fetch(TestEntity $entity)
    {
        return $this->entityManager->find(TestEntity::class, $entity->getId()); // Clean state, fetch entity from DB again
    }
}
