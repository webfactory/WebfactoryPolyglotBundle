<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslationMetadata;

class TranslationMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testIsSerializableWithoutLogger()
    {
        $metadata = $this->createMetadata();

        $serialized = serialize($metadata);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf('Webfactory\Bundle\PolyglotBundle\Doctrine\TranslationMetadata', $unserialized);
    }

    public function testIsSerializableEvenIfInjectedLoggerIsNotSerializable()
    {

    }

    /**
     * Creates metadata for testing.
     *
     * @return TranslationMetadata
     */
    private function createMetadata()
    {
        $loader = function ($annotationClass) {
            return class_exists($annotationClass, true);
        };
        AnnotationRegistry::registerLoader($loader);

        $reader = new AnnotationReader();
        $metadata = new ClassMetadataInfo('Webfactory\Bundle\PolyglotBundle\Tests\TestEntity');
        $metadata->initializeReflection(new RuntimeReflectionService());
        return TranslationMetadata::parseFromClassMetadata($metadata, $reader);
    }
}
