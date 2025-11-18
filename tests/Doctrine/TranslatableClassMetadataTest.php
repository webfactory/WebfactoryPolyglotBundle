<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslatableClassMetadata;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TestEntityTranslation;
use Webfactory\Bundle\PolyglotBundle\Tests\Functional\DatabaseFunctionalTestCase;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class TranslatableClassMetadataTest extends DatabaseFunctionalTestCase
{
    /**
     * @test
     */
    public function can_be_serialized_and_retrieved(): void
    {
        $metadata = $this->createMetadata();

        $serialize = serialize($metadata->sleep());
        $unserialized = TranslatableClassMetadata::wakeup(unserialize($serialize), new RuntimeReflectionService());

        self::assertEquals($metadata, $unserialized);
    }

    private function createMetadata(): TranslatableClassMetadata
    {
        $entityManager = $this->entityManager;

        return TranslatableClassMetadata::parseFromClass(TestEntity::class, $entityManager->getMetadataFactory());
    }
}
