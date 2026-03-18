<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclass;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_MappedSuperclassEntityTranslation;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * This tests that a property inherited from a MappedSuperclass can be declared
 * as translatable via the class-level #[TranslatedProperty] attribute on the
 * concrete entity. This makes it possible to define base classes (mapped superclasses)
 * that leave it to extending Entity subclasses whether to use Polyglot or not.
 */
class MappedSuperclassInheritanceTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::setupSchema([
            EntityInheritance_MappedSuperclass::class,
            EntityInheritance_MappedSuperclassEntity::class,
            EntityInheritance_MappedSuperclassEntityTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new EntityInheritance_MappedSuperclassEntity();
        $t = new Translatable('base text');
        $t->setTranslation('Basistext', 'de_DE');
        $entity->setText($t);

        self::import([$entity]);

        $loaded = $this->entityManager->find(EntityInheritance_MappedSuperclassEntity::class, $entity->getId());

        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_MappedSuperclassEntity();
        $entity->setText(new Translatable('base text'));
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_MappedSuperclassEntity::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_MappedSuperclassEntity::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_MappedSuperclassEntity();
        $t = new Translatable('old text');
        $t->setTranslation('alter Text', 'de_DE');
        $entity->setText($t);
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_MappedSuperclassEntity::class, $entity->getId());
        $loaded->getText()->setTranslation('new text');
        $loaded->getText()->setTranslation('neuer Text', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_MappedSuperclassEntity::class, $entity->getId());

        self::assertSame('new text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Text', $reloaded->getText()->translate('de_DE'));
    }
}
