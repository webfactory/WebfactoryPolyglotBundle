<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Log\LoggerInterface;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslatableClassMetadata;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class TranslatableClassMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testIsSerializableWithoutLogger()
    {
        $metadata = $this->createMetadata();

        $serialized = serialize($metadata);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(TranslatableClassMetadata::class, $unserialized);
    }

    public function testIsSerializableEvenIfInjectedLoggerIsNotSerializable()
    {
        $notSerializableLogger = $this->getMockBuilder('Symfony\Component\Debug\BufferingLogger', ['__sleep'])->getMock();
        $notSerializableLogger->expects($this->any())
            ->method('__sleep')
            ->will($this->throwException(new \RuntimeException('You cannot serialize me!')));

        $metadata = $this->createMetadata($notSerializableLogger);

        $serialized = serialize($metadata);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(TranslatableClassMetadata::class, $unserialized);
    }

    /**
     * Creates metadata for testing.
     *
     * @param LoggerInterface|null $logger
     *
     * @return TranslatableClassMetadataTest
     */
    private function createMetadata(LoggerInterface $logger = null)
    {
        $reader = new AnnotationReader();
        $infrastructure = new ORMInfrastructure(
            [
                '\Webfactory\Bundle\PolyglotBundle\Tests\TestEntity',
                '\Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation',
            ]
        );
        $metadata = $infrastructure->getEntityManager()->getClassMetadata('Webfactory\Bundle\PolyglotBundle\Tests\TestEntity');
        $metadata = TranslatableClassMetadata::parseFromClassMetadata($metadata, $reader);
        if ($logger !== null) {
            $metadata->setLogger($logger);
        }

        return $metadata;
    }
}
