<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslatableWithObjectData;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;

/**
 * Custom mapping type to support TranslatableWithObjectDataTest_Object instances in fields of TranslatableWithObjectDataTest_Entity
 * and TranslatableWithObjectDataTest_Translation. This is necessary since DBAL 4 removed support for the generic "object" column
 * type which was based on PHP serialization (https://github.com/doctrine/dbal/pull/5470).
 */
class ObjectType extends JsonType
{
    public const TYPE = 'my_object';

    public function convertToPHPValue($value, AbstractPlatform $platform): TranslatableWithObjectDataTest_Object
    {
        $value = parent::convertToPHPValue($value, $platform);

        if (null === $value) {
            return null;
        }

        return new TranslatableWithObjectDataTest_Object($value['text']);
    }
}
