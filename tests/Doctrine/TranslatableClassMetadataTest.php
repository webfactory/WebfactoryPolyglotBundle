<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslatableClassMetadata;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntity;
use Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class TranslatableClassMetadataTest extends TestCase
{
    /**
     * @test
     */
    public function can_be_serialized_and_retrieved(): void
    {
        $metadata = $this->createMetadata();

        $serialize = serialize($metadata->sleep());
        $unserialized = TranslatableClassMetadata::wakeup(unserialize($serialize));

        self::assertEquals($metadata, $unserialized);
    }

    private function createMetadata(): TranslatableClassMetadata
    {
        $reader = new AnnotationReader();
        $infrastructure = new ORMInfrastructure([
            TestEntity::class,
            TestEntityTranslation::class,
        ]);
        $entityManager = $infrastructure->getEntityManager();

        return TranslatableClassMetadata::parseFromClass(TestEntity::class, $reader, $entityManager->getMetadataFactory());
    }
}
