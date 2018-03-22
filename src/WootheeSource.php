<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2018, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use Psr\Log\LoggerInterface;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class WootheeSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Seld\JsonLint\JsonParser
     */
    private $jsonParser;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->jsonParser = new JsonParser();
    }

    /**
     * @param int $limit
     *
     * @return iterable|string[]
     */
    public function getUserAgents(int $limit = 0): iterable
    {
        $counter = 0;

        foreach ($this->loadFromPath() as $row) {
            if ($limit && $counter >= $limit) {
                return;
            }

            try {
                $row = $this->jsonParser->parse(
                    $row,
                    JsonParser::DETECT_KEY_CONFLICTS
                );
            } catch (ParsingException $e) {
                $this->logger->critical(new \Exception('    parsing file content failed', 0, $e));

                continue;
            }

            $agent = trim($row->target);

            if (empty($agent)) {
                continue;
            }

            yield $agent;
            ++$counter;
        }
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/woothee/woothee-testset/testsets';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $allTests = [];
        $finder   = new Finder();
        $finder->files();
        $finder->name('*.yaml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $data = Yaml::parse($file->getContents());

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (empty($row['target'])) {
                    continue;
                }

                if (array_key_exists($row['target'], $allTests)) {
                    continue;
                }

                yield json_encode($row, JSON_FORCE_OBJECT);
                $allTests[$row['target']] = 1;
            }
        }
    }
}
