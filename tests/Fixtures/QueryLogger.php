<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures;

use Psr\Log\AbstractLogger;
use Stringable;

class QueryLogger extends AbstractLogger
{
    public bool $enabled = true;
    private array $queries = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        if (str_starts_with($message, 'Executing')) {
            $this->queries[] = new Query($context['sql'], $context['params'] ?? []);
        } elseif ('Beginning transaction' === $message) {
            $this->queries[] = new Query('"START TRANSACTION"', []);
        } elseif ('Committing transaction' === $message) {
            $this->queries[] = new Query('"COMMIT"', []);
        } elseif ('Rolling back transaction' === $message) {
            $this->queries[] = new Query('"ROLLBACK"', []);
        }
    }

    public function getQueries()
    {
        return $this->queries;
    }
}
