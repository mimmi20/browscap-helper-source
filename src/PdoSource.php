<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2019, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Ua\UserAgent;
use LogicException;
use PDO;
use PDOStatement;
use RuntimeException;
use stdClass;

use function assert;
use function trim;

final class PdoSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
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
     * @return iterable<array<non-empty-string, non-empty-string>>
     *
     * @throws RuntimeException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->getAgents() as $row) {
            $ua    = UserAgent::fromUseragent(trim($row->agent));
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
        }
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<string, string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws LogicException
     * @throws RuntimeException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getProperties(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->getAgents() as $row) {
            $ua    = UserAgent::fromUseragent(trim($row->agent));
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield [
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
                    'type' => null,
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
            ];
        }
    }

    /**
     * @return iterable|stdClass[]
     */
    private function getAgents(): iterable
    {
        $sql = 'SELECT DISTINCT SQL_BIG_RESULT HIGH_PRIORITY `agent` FROM `agents` ORDER BY `lastTimeFound` DESC, `count` DESC, `idAgents` DESC';

        $driverOptions = [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY];

        $stmt = $this->pdo->prepare($sql, $driverOptions);
        assert($stmt instanceof PDOStatement);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            yield $row;
        }
    }
}
