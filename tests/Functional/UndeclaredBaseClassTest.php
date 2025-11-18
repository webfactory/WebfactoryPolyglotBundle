<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UndeclaredBaseClass\UndeclaredBaseClassTest_BaseClassTranslation;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UndeclaredBaseClass\UndeclaredBaseClassTest_EntityClass;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * This test covers a risky pattern where a base class that is neither an entity nor a mapped superclass
 * contains mapped fields, and an entity subclass inherits those.
 *
 * This is not officially supported by Doctrine ORM, but something I've seen quite a few times
 * in practice.
 */
class UndeclaredBaseClassTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::setupSchema([
            UndeclaredBaseClassTest_EntityClass::class,
            UndeclaredBaseClassTest_BaseClassTranslation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new UndeclaredBaseClassTest_EntityClass();
        $t1 = new Translatable('base text');
        $t1->setTranslation('Basistext', 'de_DE');
        $entity->setText($t1);

        self::import($entity);

        $loaded = $this->entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());

        self::assertSame('Basistext', $loaded->getText()->translate('de_DE'));
        self::assertSame('base text', $loaded->getText()->translate('en_GB'));
    }

    public function testAddTranslation(): void
    {
        $entityManager = $this->entityManager;
        $entity = new UndeclaredBaseClassTest_EntityClass();
        $entity->setText(new Translatable('base text'));
        self::import($entity);

        $loaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());

        self::assertSame('base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('Basistext', $reloaded->getText()->translate('de_DE'));
    }

    public function testUpdateTranslations(): void
    {
        $entityManager = $this->entityManager;

        $entity = new UndeclaredBaseClassTest_EntityClass();
        $t1 = new Translatable('base text');
        $t1->setTranslation('Basistext', 'de_DE');
        $entity->setText($t1);
        self::import($entity);

        $loaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());
        $loaded->getText()->setTranslation('new base text');
        $loaded->getText()->setTranslation('neuer Basistext', 'de_DE');
        $entityManager->flush();

        $entityManager->clear();
        $reloaded = $entityManager->find(UndeclaredBaseClassTest_EntityClass::class, $entity->getId());

        self::assertSame('new base text', $reloaded->getText()->translate('en_GB'));
        self::assertSame('neuer Basistext', $reloaded->getText()->translate('de_DE'));
    }
}
