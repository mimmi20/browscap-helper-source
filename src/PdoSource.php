<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use JsonException;
use PDO;
use PDOException;
use PDOStatement;
use Ramsey\Uuid\Uuid;

use function assert;
use function is_array;
use function json_decode;
use function trim;

use const JSON_THROW_ON_ERROR;

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
final class PdoSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'pdo-source';

    /** @throws void */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function isReady(string $parentMessage): bool
    {
        return true;
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws SourceException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getProperties(string $message, int &$messageLength = 0): iterable
    {
        $sql = 'SELECT DISTINCT SQL_BIG_RESULT HIGH_PRIORITY `headers`, `date`, `count`, `id` FROM `request` ORDER BY `date` DESC, `count` DESC, `id` DESC';

        $driverOptions = [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY];

        $stmt = $this->pdo->prepare($sql, $driverOptions);
        assert($stmt instanceof PDOStatement);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }

            $headerString = trim((string) $row['headers']);

            if ($headerString === '') {
                continue;
            }

            try {
                $headers = json_decode($headerString, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            $uid = Uuid::uuid4()->toString();

            yield $uid => [
                'client' => [
                    'bits' => null,
                    'isbot' => null,
                    'manufacturer' => null,
                    'modus' => null,
                    'name' => null,
                    'type' => null,
                    'version' => null,
                ],
                'device' => [
                    'brand' => null,
                    'deviceName' => null,
                    'display' => [
                        'height' => null,
                        'size' => null,
                        'touch' => null,
                        'type' => null,
                        'width' => null,
                    ],
                    'dualOrientation' => null,
                    'ismobile' => null,
                    'manufacturer' => null,
                    'marketingName' => null,
                    'simCount' => null,
                    'type' => null,
                ],
                'engine' => [
                    'manufacturer' => null,
                    'name' => null,
                    'version' => null,
                ],
                'file' => null,
                'headers' => $headers,
                'platform' => [
                    'bits' => null,
                    'manufacturer' => null,
                    'marketingName' => null,
                    'name' => null,
                    'version' => null,
                ],
                'raw' => $row,
            ];
        }
    }
}
