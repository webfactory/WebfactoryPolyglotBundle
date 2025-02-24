<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

/**
 * This listener sets the current locale for the TranslatableListener.
 * Taken from Christophe COEVOET's Doctrine Extensions Bundle.
 *
 * @author Christophe COEVOET
 */
final class LocaleListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly DefaultLocaleProvider $defaultLocaleProvider,
    ) {
    }

    /**
     * Set the translation listener locale from the request.
     *
     * This method should be attached to the kernel.request event.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->defaultLocaleProvider->setDefaultLocale($event->getRequest()->getLocale());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
