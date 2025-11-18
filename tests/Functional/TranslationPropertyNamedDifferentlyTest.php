<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslationPropertyNamedDifferently\TranslationPropertyNamedDifferently_Entity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslationPropertyNamedDifferently\TranslationPropertyNamedDifferently_Translation;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * This tests a setup where the "translation" field is named different from the
 * field in the base entity class.
 */
class TranslationPropertyNamedDifferentlyTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::setupSchema([
            TranslationPropertyNamedDifferently_Entity::class,
            TranslationPropertyNamedDifferently_Translation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new TranslationPropertyNamedDifferently_Entity();
        $translatable = new Translatable('base text');
        $translatable->setTranslation('Basistext', 'de_DE');
        $entity->setText($translatable);

        self::import($entity);

        $loaded = $this->entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());

        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->entityManager;
        $entity = new TranslationPropertyNamedDifferently_Entity();
        $entity->setText(new Translatable('base text'));
        self::import($entity);

        $loaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->entityManager;

        $entity = new TranslationPropertyNamedDifferently_Entity();
        $translatable = new Translatable('base text');
        $translatable->setTranslation('Basistext', 'de_DE');
        $entity->setText($translatable);
        self::import($entity);

        $loaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(TranslationPropertyNamedDifferently_Entity::class, $entity->getId());

        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
    }
}
