<?php

/**
 * Console command: display Alma collections.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Console
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindHttp\HttpService;

use function array_filter;
use function count;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function rtrim;
use function simplexml_load_string;
use function str_repeat;
use function trim;

/**
 * Console command: display Alma collections.
 *
 * @category VuFind
 * @package  Console
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/alma_collections',
    description: 'Display a list of Alma collections'
)]
class AlmaCollectionsCommand extends Command
{
    /**
     * Alma configuration.
     *
     * @var array
     */
    protected $almaConfig;

    /**
     * HTTP service.
     *
     * @var HttpService
     */
    protected $httpService;

    /**
     * Constructor.
     *
     * @param array       $almaConfig  Alma configuration
     * @param HttpService $httpService HTTP service
     * @param string|null $name        The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(
        array $almaConfig,
        HttpService $httpService,
        $name = null
    ) {
        $this->almaConfig = $almaConfig;
        $this->httpService = $httpService;
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp(
                'Displays an overview of the collections defined in Alma, including the collection '
                . 'hierarchy. The Alma API base URL and key are read from the [Catalog] section of '
                . 'config/vufind/Alma.ini.'
            )
            ->addOption(
                'level',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of collection tree levels to retrieve (default: 1, i.e. only top-level '
                . 'collections; 2 includes immediate sub-collections)'
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiBaseUrl = $this->almaConfig['Catalog']['apiBaseUrl'] ?? null;
        $apiKey = $this->almaConfig['Catalog']['apiKey'] ?? null;
        if (empty($apiBaseUrl) || empty($apiKey)) {
            $output->writeln(
                'The Alma API base URL and key must be configured in the [Catalog] section of '
                . 'config/vufind/Alma.ini.'
            );
            return self::FAILURE;
        }

        $params = ['apikey' => $apiKey];
        if (null !== ($level = $input->getOption('level'))) {
            $params['level'] = $level;
        }
        $timeout = (int)($this->almaConfig['Catalog']['http_timeout'] ?? 30);

        $output->writeln(
            'Fetching collections from Alma...',
            OutputInterface::VERBOSITY_VERBOSE
        );
        try {
            $response = $this->httpService
                ->get(rtrim($apiBaseUrl, '/') . '/bibs/collections', $params, $timeout);
        } catch (\VuFindHttp\Exception\RuntimeException $e) {
            $output->writeln('Error accessing the Alma API: ' . $e->getMessage());
            return self::FAILURE;
        }
        $body = (string)$response->getBody();
        $output->writeln(
            'Alma API response: ' . $body,
            OutputInterface::VERBOSITY_DEBUG
        );
        if (!$response->isSuccess()) {
            $errorMessage = $this->extractError($body);
            $output->writeln(
                'Error accessing the Alma API: HTTP ' . $response->getStatusCode()
                . ($errorMessage ? ' (' . $errorMessage . ')' : '')
            );
            return self::FAILURE;
        }
        $data = $this->parseResponse($body);
        if (null === $data) {
            $output->writeln('Error parsing the Alma API response.');
            return self::FAILURE;
        }
        if (!empty($data['errorsExist']) && 'false' !== $data['errorsExist']) {
            $output->writeln('Error returned by the Alma API: ' . json_encode($data));
            return self::FAILURE;
        }

        $rows = [];
        foreach ($this->parseCollectionList($data)['items'] as $item) {
            $this->flattenCollection($item, 0, $rows);
        }
        $this->printOverview($rows, $output);

        return self::SUCCESS;
    }

    /**
     * Parse an Alma XML response and return it as an array.
     *
     * @param string $body Response body
     *
     * @return ?array Parsed response, or null if parsing failed
     */
    protected function parseResponse(string $body): ?array
    {
        $xml = simplexml_load_string($body);
        if (false === $xml) {
            return null;
        }
        $json = json_encode($xml);
        if (false === $json) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Extract the list of collections and the total count from a response.
     *
     * @param array $data Parsed response
     *
     * @return array{items: array, total: int}
     */
    protected function parseCollectionList(array $data): array
    {
        // Direct response: <collections total_record_count="N"> with <collection> children.
        $items = $this->getElementList($data, 'collection');
        $total = (int)($data['@attributes']['total_record_count'] ?? 0);
        // Fallback: a web_service_result wrapper containing a <collections> element.
        if (empty($items) && isset($data['collections'])) {
            $items = $this->getElementList($data['collections'], 'collection');
            $total = (int)($data['total_record_count'] ?? 0);
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Extract the error message(s) from an Alma error response.
     *
     * @param string $body Response body
     *
     * @return string
     */
    protected function extractError(string $body): string
    {
        $data = $this->parseResponse($body) ?? [];
        $messages = [];
        foreach ($this->getElementList($data['errorList'] ?? [], 'error') as $error) {
            $messages[] = $this->elementValue($error['errorMessage'] ?? null);
        }
        return implode('; ', array_filter($messages));
    }

    /**
     * Return the elements with the given key as a list.
     *
     * @param array  $data Data
     * @param string $key  Element name
     *
     * @return array
     */
    protected function getElementList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return [];
        }
        return isset($value[0]) ? $value : [$value];
    }

    /**
     * Flatten a collection and its sub-collections into table rows.
     *
     * @param array $collection Collection data
     * @param int   $depth      Collection tree depth
     * @param array $rows       Table rows (passed by reference)
     *
     * @return void
     */
    protected function flattenCollection(array $collection, int $depth, array &$rows): void
    {
        $rows[] = [
            str_repeat('  ', $depth) . $this->elementValue($collection['pid'] ?? null),
            $this->elementValue($collection['parent_pid'] ?? null),
            $this->elementValue($collection['mms_id'] ?? null),
            $this->elementValue($collection['name'] ?? null),
            $this->truncate($this->elementValue($collection['description'] ?? null)),
        ];
        foreach ($this->getElementList($collection['collections'] ?? [], 'collection') as $child) {
            $this->flattenCollection($child, $depth + 1, $rows);
        }
    }

    /**
     * Return the value of an XML element, or an empty string for empty elements.
     *
     * @param mixed $value Element value
     *
     * @return string
     */
    protected function elementValue($value): string
    {
        return is_array($value) ? '' : trim((string)($value ?? ''));
    }

    /**
     * Truncate a string for table display.
     *
     * @param string $value  String to truncate
     * @param int    $length Maximum length
     *
     * @return string
     */
    protected function truncate(string $value, int $length = 60): string
    {
        if (mb_strlen($value) > $length) {
            return mb_substr($value, 0, $length - 3) . '...';
        }
        return $value;
    }

    /**
     * Print the collection overview table.
     *
     * @param array           $rows   Table rows
     * @param OutputInterface $output Output object
     *
     * @return void
     */
    protected function printOverview(array $rows, OutputInterface $output): void
    {
        if (empty($rows)) {
            $output->writeln('No collections found.');
            return;
        }
        $table = new Table($output);
        $table->setHeaders(['PID', 'Parent', 'MMS ID', 'Name', 'Description']);
        foreach ($rows as $row) {
            $table->addRow($row);
        }
        $table->render();
        $output->writeln(count($rows) . ' collection(s) listed.');
    }
}
