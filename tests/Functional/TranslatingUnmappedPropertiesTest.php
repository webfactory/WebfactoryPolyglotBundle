<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UnmappedProperties\TranslatingUnmappedPropertiesTest_Entity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UnmappedProperties\TranslatingUnmappedPropertiesTest_Translation;
use Webfactory\Bundle\PolyglotBundle\Translatable;

/**
 * This test covers that also properties which are not mapped Doctrine fields
 * can be marked as translatable and will be handled by the PolyglotListener.
 *
 * This is useful when these fields are managed or updated by e.g. lifecycle callbacks
 * or other Doctrine event listeners.
 */
class TranslatingUnmappedPropertiesTest extends DatabaseFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::setupSchema([
            TranslatingUnmappedPropertiesTest_Entity::class,
            TranslatingUnmappedPropertiesTest_Translation::class,
        ]);
    }

    public function testPersistAndReloadEntity(): void
    {
        $entity = new TranslatingUnmappedPropertiesTest_Entity();
        $entity->text = new Translatable('base text');
        $entity->text->setTranslation('Basistext', 'de_DE');

        self::import($entity);

        $loaded = $this->entityManager->find(TranslatingUnmappedPropertiesTest_Entity::class, $entity->id);

        self::assertSame('Basistext', $loaded->text->translate('de_DE'));
        self::assertSame('base text', $loaded->text->translate('en_GB'));
    }
}
