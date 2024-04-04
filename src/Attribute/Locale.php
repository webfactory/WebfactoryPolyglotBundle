<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final class Locale
{
    private ?string $primary;

    public function __construct(string $primary = null)
    {
        $this->primary = $primary;
    }

    public function getPrimary(): ?string
    {
        return $this->primary;
    }
}
