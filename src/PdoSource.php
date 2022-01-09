<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use PDO;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function assert;
use function is_array;
use function trim;

final class PdoSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'pdo-source';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
     * @throws RuntimeException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getProperties(string $message, int &$messageLength = 0): iterable
    {
        $sql = 'SELECT DISTINCT SQL_BIG_RESULT HIGH_PRIORITY `agent` FROM `agents` ORDER BY `lastTimeFound` DESC, `count` DESC, `idAgents` DESC';

        $driverOptions = [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY];

        $stmt = $this->pdo->prepare($sql, $driverOptions);
        assert($stmt instanceof PDOStatement);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }

            $agent = trim($row['agent']);

            if ('' === $agent) {
                continue;
            }

            $uid = Uuid::uuid4()->toString();

            yield $uid => [
                'headers' => ['user-agent' => $agent],
                'device' => [
                    'deviceName' => null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'dualOrientation' => null,
                    'type' => null,
                    'simCount' => null,
                    'ismobile' => null,
                ],
                'client' => [
                    'name' => null,
                    'modus' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => null,
                    'marketingName' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ],
                'raw' => $row,
                'file' => null,
            ];
        }
    }
}
