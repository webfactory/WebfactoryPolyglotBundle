<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_BaseEntityClass;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_BaseEntityClassTranslation;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_ChildEntityClass;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance\EntityInheritance_ChildEntityClassTranslation;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * This tests translations for different fields in an inheritance hierarchy. For every
 * entity class in the hierarchy, a dedicated translations class has to be used.
 */
class EntityInheritanceTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::setupSchema([
            EntityInheritance_BaseEntityClass::class,
            EntityInheritance_BaseEntityClassTranslation::class,
            EntityInheritance_ChildEntityClass::class,
            EntityInheritance_ChildEntityClassTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new EntityInheritance_ChildEntityClass();
        $t1 = new Translatable('base text');
        $t1->setTranslation('Basistext', 'de_DE');
        $entity->setText($t1);

        $t2 = new Translatable('extra text');
        $t2->setTranslation('Extratext', 'de_DE');
        $entity->setExtra($t2);

        self::import([$entity]);

        $loaded = $this->entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());

        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
        self::assertSame('Extratext', $loaded->getExtraText()->translate('de_DE'));
        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
        self::assertSame('extra text', $loaded->getExtraText()->translate('en_GB'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_ChildEntityClass();
        $entity->setText(new Translatable('base text'));
        $entity->setExtra(new Translatable('extra text'));
        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $loaded->getExtraText()->setTranslation('Extratext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();

        $reloaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());

        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
        self::assertSame('Extratext', $reloaded->getExtraText()->translate('de_DE'));
        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('extra text', $reloaded->getExtraText()->translate('en_GB'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->entityManager;

        $entity = new EntityInheritance_ChildEntityClass();
        $t1 = new Translatable('old base text');
        $t1->setTranslation('alter Basistext', 'de_DE');
        $entity->setText($t1);

        $t2 = new Translatable('old extra text');
        $t2->setTranslation('alter Extratext', 'de_DE');
        $entity->setExtra($t2);

        self::import([$entity]);

        $loaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $loaded->getExtraText()->setTranslation('new extra text');
        $loaded->getExtraText()->setTranslation('neuer Extratext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(EntityInheritance_ChildEntityClass::class, $entity->getId());

        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
        self::assertSame('neuer Extratext', $reloaded->getExtraText()->translate('de_DE'));
        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('new extra text', $reloaded->getExtraText()->translate('en_GB'));
    }
}
