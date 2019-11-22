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
use Psr\Log\LoggerInterface;

final class PdoSource implements SourceInterface
{
    use GetUserAgentsTrait;

    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \PDO                     $pdo
     */
    public function __construct(LoggerInterface $logger, \PDO $pdo)
    {
        $this->logger = $logger;
        $this->pdo    = $pdo;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'PDO';
    }

    /**
     * @return array[]|iterable
     */
    public function getHeaders(): iterable
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
     * @return array[]|iterable
     */
    public function getProperties(): iterable
    {
        foreach ($this->getAgents() as $row) {
            $ua    = UserAgent::fromUseragent(trim($row->agent));
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => [
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
                    'market' => [
                        'regions' => null,
                        'countries' => null,
                        'vendors' => null,
                    ],
                    'connections' => null,
                    'ismobile' => null,
                ],
                'browser' => [
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
     * @return iterable|\stdClass[]
     */
    private function getAgents(): iterable
    {
        $sql = 'SELECT DISTINCT SQL_BIG_RESULT HIGH_PRIORITY `agent` FROM `agents` ORDER BY `lastTimeFound` DESC, `count` DESC, `idAgents` DESC';

        $driverOptions = [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY];

        /** @var \PDOStatement $stmt */
        $stmt = $this->pdo->prepare($sql, $driverOptions);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            yield $row;
        }
    }
}
