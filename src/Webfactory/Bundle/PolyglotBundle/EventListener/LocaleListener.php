<?php

namespace Webfactory\Bundle\PolyglotBundle\EventListener;

use Webfactory\Bundle\PolyglotBundle\Doctrine\TranslatableListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This listeners sets the current locale for the TranslatableListener.
 * Taken from Christophe COEVOET's Doctrine Extensions Bundle.
 *
 * @author Christophe COEVOET
 */
class LocaleListener implements EventSubscriberInterface
{
    private $translatableListener;

    public function __construct(TranslatableListener $translatableListener)
    {
        $this->translatableListener = $translatableListener;
    }

    /**
     * Set the translation listener locale from the request.
     *
     * This method should be attached to the kernel.request event.
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->translatableListener->setDefaultLocale($event->getRequest()->getLocale());
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => 'onKernelRequest',
        );
    }
}
