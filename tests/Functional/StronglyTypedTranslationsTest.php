<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\StronglyTyped\StronglyTypedTranslationsTest_Entity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\StronglyTyped\StronglyTypedTranslationsTest_Translation;

class StronglyTypedTranslationsTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::setupSchema([
            StronglyTypedTranslationsTest_Entity::class,
            StronglyTypedTranslationsTest_Translation::class,
        ]);
    }

    /**
     * @test
     */
    public function persist_new_values(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $entity->text->setTranslation('text de_DE', 'de_DE');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $resultPrimaryTable = $this->entityManager->getConnection()->executeQuery('SELECT * FROM StronglyTypedTranslationsTest_Entity')->fetchAllAssociative();
        self::assertCount(1, $resultPrimaryTable);
        self::assertSame('text en_GB', $resultPrimaryTable[0]['text']);

        $resultTranslationsTable = $this->entityManager->getConnection()->executeQuery('SELECT * FROM StronglyTypedTranslationsTest_Translation')->fetchAllAssociative();
        self::assertCount(1, $resultTranslationsTable);
        self::assertSame('text de_DE', $resultTranslationsTable[0]['text']);

        self::assertSame('text en_GB', $entity->text->translate('en_GB'));
        self::assertSame('text de_DE', $entity->text->translate('de_DE'));
    }

    /**
     * @test
     */
    public function load_database_values(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $entity->text->setTranslation('text de_DE', 'de_DE');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(StronglyTypedTranslationsTest_Entity::class, $entity->id);

        self::assertSame('text en_GB', $reloaded->text->translate('en_GB'));
        self::assertSame('text de_DE', $reloaded->text->translate('de_DE'));
    }

    /**
     * @test
     */
    public function double_flush_without_changes_for_new_entity_does_not_update(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $count = \count($this->getQueries());

        $this->entityManager->flush();

        self::assertCount($count, $this->getQueries());
    }

    /**
     * @test
     */
    public function flushing_loaded_entity_without_changes_does_not_update(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->entityManager->find(StronglyTypedTranslationsTest_Entity::class, $entity->id);
        $count = \count($this->getQueries());
        $this->entityManager->flush();

        self::assertCount($count, $this->getQueries());
    }

    /**
     * @test
     */
    public function flush_new_entity_two_times_with_same_value_does_not_update(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $count = \count($this->getQueries());

        $entity->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->flush();

        self::assertCount($count, $this->getQueries());
    }

    /**
     * @test
     */
    public function flush_loaded_entity_with_same_value_does_not_update(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(StronglyTypedTranslationsTest_Entity::class, $entity->id);
        $count = \count($this->getQueries());

        $reloaded->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->flush();

        self::assertCount($count, $this->getQueries());
    }

    /**
     * @test
     */
    public function changing_values_for_persisted_entity(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $entity->text->setTranslation('text de_DE', 'de_DE');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(StronglyTypedTranslationsTest_Entity::class, $entity->id);

        $reloaded->text->setTranslation('changed text en_GB', 'en_GB');
        $reloaded->text->setTranslation('changed text de_DE', 'de_DE');

        $this->entityManager->flush();

        $resultPrimaryTable = $this->entityManager->getConnection()->executeQuery('SELECT * FROM StronglyTypedTranslationsTest_Entity')->fetchAllAssociative();
        self::assertCount(1, $resultPrimaryTable);
        self::assertSame('changed text en_GB', $resultPrimaryTable[0]['text']);

        $resultTranslationsTable = $this->entityManager->getConnection()->executeQuery('SELECT * FROM StronglyTypedTranslationsTest_Translation')->fetchAllAssociative();
        self::assertCount(1, $resultTranslationsTable);
        self::assertSame('changed text de_DE', $resultTranslationsTable[0]['text']);

        self::assertSame('changed text en_GB', $reloaded->text->translate('en_GB'));
        self::assertSame('changed text de_DE', $reloaded->text->translate('de_DE'));
    }

    /**
     * @test
     */
    public function flush_updated_entity_two_times_does_not_update(): void
    {
        $entity = new StronglyTypedTranslationsTest_Entity();
        $entity->text->setTranslation('text en_GB', 'en_GB');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(StronglyTypedTranslationsTest_Entity::class, $entity->id);

        $reloaded->text->setTranslation('changed text en_GB', 'en_GB');
        $this->entityManager->flush();
        $count = \count($this->getQueries());

        $this->entityManager->flush();

        self::assertCount($count, $this->getQueries());
    }
}
