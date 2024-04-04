<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Doctrine type to support mapping database string types (VARCHAR, TEXT etc.)
 * as the "primary" translation values into fields that declare {@see TranslatableInterface}
 * as their only type.
 */
class TranslatableStringType extends Type
{
    public const NAME = 'translatable_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($column['options']['use_text_column'] ?? false) {
            return $platform->getClobTypeDeclarationSQL($column);
        }

        if (method_exists($platform, 'getStringTypeDeclarationSQL')) {
            return $platform->getStringTypeDeclarationSQL($column);
        } else {
            // deprecated as of doctrine/dbal 3.4.0
            return $platform->getVarcharTypeDeclarationSQL($column);
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof UninitializedPersistentTranslatable) {
            throw new RuntimeException('Unexpected type');
        }

        return $value->getPrimaryValue();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): UninitializedPersistentTranslatable
    {
        return new UninitializedPersistentTranslatable($value);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
