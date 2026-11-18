<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Entity\TestProperties;
use JsonException;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

use function assert;
use function is_array;
use function is_string;
use function json_decode;
use function mb_str_pad;
use function mb_strlen;
use function mb_strtolower;
use function mb_trim;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_RIGHT;

/**
 * use this schema
 * <code>
 * CREATE TABLE IF NOT EXISTS `request` (
 * `id` int(11) NOT NULL AUTO_INCREMENT,
 * `date` date NOT NULL,
 * `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
 * `count` int(11) NOT NULL DEFAULT 0,
 * PRIMARY KEY (`id`) USING BTREE,
 * UNIQUE KEY `idx_headers` (`headers`(190))
 * ) ENGINE=InnoDB AUTO_INCREMENT=228817 DEFAULT CHARSET=utf8mb4 CHECKSUM=1 ROW_FORMAT=COMPACT;
 * </code>
 */
final readonly class PdoSource implements SourceInterface
{
    use GetUserAgentsTrait;

    private const string NAME = 'pdo-source';

    /** @throws void */
    public function __construct(private PDO $pdo, private LoggerInterface $consoleLogger, private string $dbName = '')
    {
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        return true;
    }

    /** @throws void */
    #[Override]
    public function getName(): string
    {
        return self::NAME . '(' . $this->dbName . ')';
    }

    /**
     * @return iterable<non-empty-string, TestProperties>
     *
     * @throws Exception\SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading database %s', $this->dbName);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->consoleLogger->info(
            mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
        );

        $sql = 'SELECT DISTINCT SQL_BIG_RESULT HIGH_PRIORITY `headers`, `date`, `count`, `id` FROM `request` ORDER BY `date` DESC, `count` DESC, `id` DESC';

        $driverOptions = [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY];

        try {
            $stmt = $this->pdo->prepare($sql, $driverOptions);
        } catch (PDOException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        assert($stmt instanceof PDOStatement);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row) || !is_string($row['headers'])) {
                    continue;
                }

                $headerString = mb_trim((string) $row['headers']);

                if ($headerString === '') {
                    continue;
                }

                try {
                    $headers = json_decode(
                        $headerString,
                        associative: true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                } catch (JsonException $e) {
                    throw new Exception\SourceException($e->getMessage(), 0, $e);
                }

                if (!is_array($headers)) {
                    continue;
                }

                $lowerHeaders = [];

                foreach ($headers as $key => $value) {
                    if (!is_string($key) || $key === '') {
                        continue;
                    }

                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    $lowerHeaders[mb_strtolower($key)] = $value;
                }

                if ($lowerHeaders === []) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => new TestProperties(
                    headers: $lowerHeaders,
                    device: [
                        'architecture' => null,
                        'brand' => null,
                        'deviceName' => null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'simCount' => null,
                        'type' => null,
                        'bits' => null,
                    ],
                    os: [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    client: [
                        'bits' => null,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => null,
                        'type' => null,
                        'version' => null,
                    ],
                    engine: [
                        'manufacturer' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    file: null,
                    dateFirst: is_string($row['date']) ? $row['date'] : null,
                    dateLast: is_string($row['date']) ? $row['date'] : null,
                    raw: $row,
                );
            }
        } catch (PDOException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }
    }
}
