<?php
/**
 * This file is part of the prooph/pdo-event-store.
 * (c) 2016-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStore\Pdo\PersistenceStrategy;

use Iterator;
use Prooph\EventStore\Pdo\Exception;
use Prooph\EventStore\Pdo\PersistenceStrategy;
use Prooph\EventStore\Pdo\Util\PostgresHelper;
use Prooph\EventStore\StreamName;

final class PostgresAggregateStreamStrategy implements PersistenceStrategy
{
    use PostgresHelper;

    /**
     * @param string $tableName
     * @return string[]
     */
    public function createSchema(string $tableName): array
    {
        $tableName = $this->quoteIdent($tableName);

        $statement = <<<EOT
CREATE TABLE $tableName (
    no BIGSERIAL,
    event_id UUID NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    metadata JSONB NOT NULL,
    created_at TIMESTAMP(6) NOT NULL,
    PRIMARY KEY (no),
    UNIQUE (event_id)
);
EOT;

        return [
            $statement,
            "CREATE UNIQUE INDEX on $tableName ((metadata->>'_aggregate_version'));",
        ];
    }

    public function columnNames(): array
    {
        return [
            'no',
            'event_id',
            'event_name',
            'payload',
            'metadata',
            'created_at',
        ];
    }

    public function prepareData(Iterator $streamEvents): array
    {
        $data = [];

        foreach ($streamEvents as $event) {
            if (! isset($event->metadata()['_aggregate_version'])) {
                throw new Exception\RuntimeException('_aggregate_version is missing in metadata');
            }

            $data[] = $event->metadata()['_aggregate_version'];
            $data[] = $event->uuid()->toString();
            $data[] = $event->messageName();
            $data[] = json_encode($event->payload());
            $data[] = json_encode($event->metadata());
            $data[] = $event->createdAt()->format('Y-m-d\TH:i:s.u');
        }

        return $data;
    }

    public function generateTableName(StreamName $streamName): string
    {
        return implode('.', array_filter([
            $this->extractSchema($streamName->toString()),
            '_' . sha1($streamName->toString()),
        ]));
    }
}
