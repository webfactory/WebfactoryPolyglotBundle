<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Functional;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Webfactory\Bundle\PolyglotBundle\Doctrine\PolyglotListener;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

abstract class FunctionalTestBase extends TestCase
{
    protected ORMInfrastructure $infrastructure;
    protected EntityManagerInterface $entityManager;
    protected DefaultLocaleProvider $defaultLocaleProvider;

    protected function setupOrmInfrastructure(array $classes): void
    {
        $this->infrastructure = ORMInfrastructure::createOnlyFor($classes);
        $this->entityManager = $this->infrastructure->getEntityManager();
        $this->defaultLocaleProvider = new DefaultLocaleProvider('en_GB');

        $this->entityManager->getEventManager()->addEventSubscriber(
            new PolyglotListener(new AnnotationReader(), $this->defaultLocaleProvider)
        );
    }
}
