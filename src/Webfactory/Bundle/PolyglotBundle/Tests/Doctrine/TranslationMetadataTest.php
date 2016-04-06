<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Log\LoggerInterface;
use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslationMetadata;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

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
        $notSerializableLogger = $this->getMock('Symfony\Component\Debug\BufferingLogger', array('__sleep'));
        $notSerializableLogger->expects($this->any())
            ->method('__sleep')
            ->will($this->throwException(new \RuntimeException('You cannot serialize me!')));

        $metadata = $this->createMetadata($notSerializableLogger);

        $serialized = serialize($metadata);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf('Webfactory\Bundle\PolyglotBundle\Doctrine\TranslationMetadata', $unserialized);
    }

    /**
     * Creates metadata for testing.
     *
     * @param LoggerInterface|null $logger
     * @return TranslationMetadata
     */
    private function createMetadata(LoggerInterface $logger = null)
    {
        $reader = new AnnotationReader();
        $infrastructure = new ORMInfrastructure(
            array(
                '\Webfactory\Bundle\PolyglotBundle\Tests\TestEntity',
                '\Webfactory\Bundle\PolyglotBundle\Tests\TestEntityTranslation',
            )
        );
        $metadata =$infrastructure->getEntityManager()->getClassMetadata('Webfactory\Bundle\PolyglotBundle\Tests\TestEntity');
        $translationMetadata =  TranslationMetadata::parseFromClassMetadata($metadata, $reader);
        if ($logger !== null) {
            $translationMetadata->setLogger($logger);
        }
        return $translationMetadata;
    }
}
