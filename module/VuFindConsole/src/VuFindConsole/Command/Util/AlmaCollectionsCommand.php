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

use DOMDocument;
use DOMNode;
use DOMXPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindHttp\HttpService;

use function array_filter;
use function array_merge;
use function count;
use function end;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function microtime;
use function mkdir;
use function round;
use function rtrim;
use function simplexml_load_string;
use function sleep;
use function str_repeat;
use function str_replace;
use function time;
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
        $help = [
            'Displays an overview of the collections defined in Alma, including the collection',
            'hierarchy. The Alma API base URL and key are read from the [Catalog] section of',
            'config/vufind/Alma.ini.',
            '',
            'Typical workflow to index the collections and their member records:',
            '',
            '  php util/alma_collections.php --output=$VUFIND_LOCAL_DIR/harvest/Collections --level=2',
            '  $VUFIND_HOME/import-marc.sh $VUFIND_LOCAL_DIR/harvest/Collections/collection-*.xml',
            '  php util/createHierarchyTrees.php',
            '',
            'Each collection is written to a file collection-<mms_id>.xml. In addition, the',
            'records of all member titles are downloaded (GET /bibs/collections/{pid}/bibs)',
            'and written to files collection-member-<mms_id>.xml. The collection records are',
            'augmented with a MARC 520 summary field containing the collection description,',
            'and both the collection and the member records get a local MARC 996 field with the',
            'VuFind hierarchy fields; see the mappings in import/marc.properties.',
            '',
            'To display the member records on the collection page, enable the Collections',
            'module (collections = true in the [Collections] section of config.ini) and add',
            'the CollectionList tab in RecordTabs.ini.',
        ];
        $this
            ->setHelp(implode("\n", $help))
            ->addOption(
                'level',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of collection tree levels to retrieve (default: 1, i.e. only top-level '
                . 'collections; 2 includes immediate sub-collections)'
            )
            ->addOption(
                'pid',
                null,
                InputOption::VALUE_REQUIRED,
                'PID of a single collection to display or download instead of all collections. '
                . 'A selected sub-collection is treated as the top of its own hierarchy.'
            )
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'Write the MARCXML records of the collections and their member titles to the '
                . 'given directory instead of displaying the overview (default: '
                . 'local/harvest/Collections). Each collection is stored in a file '
                . 'collection-<mms_id>.xml and each member title in a file '
                . 'collection-member-<mms_id>.xml. The records are augmented with a MARC 520 '
                . 'summary field containing the collection description and a local MARC 996 '
                . 'field containing the VuFind hierarchy fields; see the mappings in '
                . 'import/marc.properties.'
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

        $body = $this->request('bibs/collections', $params, $timeout, $output);
        if (null === $body) {
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

        $items = $this->parseCollectionList($data)['items'];
        if (null !== ($pid = $input->getOption('pid'))) {
            $found = $this->findCollection($items, $pid);
            if (null === $found) {
                $output->writeln('No collection with PID ' . $pid . ' found.');
                return self::FAILURE;
            }
            $items = [$found];
        }

        if (null !== ($outputDir = $input->getOption('output'))) {
            return $this->downloadCollections(
                $items,
                [],
                $outputDir,
                $apiKey,
                $timeout,
                $output
            );
        }

        $rows = [];
        foreach ($items as $item) {
            $this->flattenCollection($item, 0, $rows);
        }
        $this->printOverview($rows, $output);

        return self::SUCCESS;
    }

    /**
     * Perform a GET request against the Alma API.
     *
     * Transient errors (HTTP 429, 500 and 503) and network errors are retried
     * with an increasing delay before giving up. The number of retries and the
     * initial delay can be configured via http_retries and http_retry_sleep in
     * the [Catalog] section of Alma.ini.
     *
     * @param string          $path    API path (relative to the base URL)
     * @param array           $params  Query string parameters
     * @param int             $timeout Timeout in seconds
     * @param OutputInterface $output  Output object
     *
     * @return ?string Response body, or null on error
     */
    protected function request(
        string $path,
        array $params,
        int $timeout,
        OutputInterface $output
    ): ?string {
        $apiBaseUrl = $this->almaConfig['Catalog']['apiBaseUrl'] ?? null;
        $retries = (int)($this->almaConfig['Catalog']['http_retries'] ?? 3);
        $retrySleep = (int)($this->almaConfig['Catalog']['http_retry_sleep'] ?? 1);
        $url = rtrim($apiBaseUrl, '/') . '/' . $path;
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $output->writeln(
                'Fetching ' . $path . ' from Alma'
                . ($attempt > 0 ? ' (attempt ' . ($attempt + 1)
                    . ' of ' . ($retries + 1) . ')' : '') . '...',
                OutputInterface::VERBOSITY_VERBOSE
            );
            $startTime = microtime(true);
            try {
                $response = $this->httpService->get($url, $params, $timeout);
            } catch (\VuFindHttp\Exception\RuntimeException $e) {
                if ($attempt < $retries) {
                    $delay = $retrySleep * (2 ** $attempt);
                    $output->writeln(
                        'Error accessing the Alma API: ' . $e->getMessage()
                        . '; retrying in ' . $delay . 's...'
                    );
                    sleep($delay);
                    continue;
                }
                $output->writeln('Error accessing the Alma API: ' . $e->getMessage());
                return null;
            }
            $statusCode = $response->getStatusCode();
            $elapsed = microtime(true) - $startTime;
            $body = (string)$response->getBody();
            $output->writeln(
                'Alma API response: HTTP ' . $statusCode . ' in '
                . round($elapsed, 2) . 's: ' . $this->redactApiKey($body),
                OutputInterface::VERBOSITY_DEBUG
            );
            if (!$response->isSuccess()) {
                $errorMessage = $this->redactApiKey($this->extractError($body));
                if ($attempt < $retries && in_array($statusCode, [429, 500, 503], true)) {
                    $delay = $retrySleep * (2 ** $attempt);
                    $output->writeln(
                        'Error accessing the Alma API: HTTP ' . $statusCode
                        . ($errorMessage ? ' (' . $errorMessage . ')' : '')
                        . '; retrying in ' . $delay . 's...'
                    );
                    sleep($delay);
                    continue;
                }
                $output->writeln(
                    'Error accessing the Alma API: HTTP ' . $statusCode
                    . ($errorMessage ? ' (' . $errorMessage . ')' : '')
                );
                return null;
            }
            return $body;
        }
        return null;
    }

    /**
     * Find a collection by PID in a collection tree.
     *
     * @param array  $items Collection list
     * @param string $pid   PID to search for
     *
     * @return ?array The collection, or null if not found
     */
    protected function findCollection(array $items, string $pid): ?array
    {
        foreach ($items as $item) {
            if ($this->elementValue($item['pid'] ?? null) === $pid) {
                return $item;
            }
            $found = $this->findCollection(
                $this->getElementList($item['collections'] ?? [], 'collection'),
                $pid
            );
            if (null !== $found) {
                return $found;
            }
        }
        return null;
    }

    /**
     * Download the MARCXML records of the given collections.
     *
     * @param array           $items     Collection list
     * @param array           $ancestors MMS IDs of the parent collections
     * @param string          $outputDir Output directory
     * @param string          $apiKey    Alma API key
     * @param int             $timeout   Timeout in seconds
     * @param OutputInterface $output    Output object
     *
     * @return int Command result code
     */
    protected function downloadCollections(
        array $items,
        array $ancestors,
        string $outputDir,
        string $apiKey,
        int $timeout,
        OutputInterface $output
    ): int {
        if (empty($items)) {
            $output->writeln('No collections found.');
            return self::SUCCESS;
        }
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            $output->writeln('Error creating output directory: ' . $outputDir);
            return self::FAILURE;
        }
        $written = 0;
        $skipped = 0;
        $failed = 0;
        $members = [];
        $memberRecords = [];
        $writtenMembers = [];
        $startTime = time();
        foreach ($items as $item) {
            $result = $this->downloadCollection(
                $item,
                $ancestors,
                $outputDir,
                $apiKey,
                $timeout,
                $output,
                $members,
                $memberRecords
            );
            $written += $result['written'];
            $skipped += $result['skipped'];
            $failed += $result['failed'];
            // Write the member records gathered so far. This fills the output
            // directory while the download is still running and keeps the number
            // of member records held in memory limited to the current tree.
            $memberResult = $this->writeMembers(
                $members,
                $memberRecords,
                $writtenMembers,
                $outputDir,
                $output
            );
            $failed += $memberResult['failed'];
            $output->writeln(
                count($writtenMembers) . ' member record(s) written so far.'
            );
        }
        $memberWritten = count($writtenMembers);
        $message = $written . ' collection record(s) and ' . $memberWritten
            . ' member record(s) written to ' . $outputDir
            . ' (' . (time() - $startTime) . 's).';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' skipped.';
        }
        if ($failed > 0) {
            $message .= ' ' . $failed . ' failed.';
        }
        $output->writeln($message);
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Download the MARCXML record of a single collection, its members and its sub-collections.
     *
     * @param array           $collection    Collection data
     * @param array           $ancestors     Parent collections (arrays with mms_id and name)
     * @param string          $outputDir     Output directory
     * @param string          $apiKey        Alma API key
     * @param int             $timeout       Timeout in seconds
     * @param OutputInterface $output        Output object
     * @param array           $members       Member slots by MMS ID (passed by reference)
     * @param array           $memberRecords MARCXML of the member records by MMS ID
     *                                       (passed by reference)
     *
     * @return array{written: int, skipped: int, failed: int} Counts
     */
    protected function downloadCollection(
        array $collection,
        array $ancestors,
        string $outputDir,
        string $apiKey,
        int $timeout,
        OutputInterface $output,
        array &$members,
        array &$memberRecords
    ): array {
        $result = ['written' => 0, 'skipped' => 0, 'failed' => 0];
        $mmsId = $this->elementValue($collection['mms_id'] ?? null);
        $name = $this->elementValue($collection['name'] ?? null);
        $description = $this->elementValue($collection['description'] ?? null);
        if ('' === $mmsId) {
            $output->writeln(
                'Skipping collection without MMS ID (PID '
                . $this->elementValue($collection['pid'] ?? null) . ').'
            );
            $result['skipped']++;
        } else {
            $output->writeln(
                'Processing collection "' . $name . '" (MMS ' . $mmsId . ').'
            );
            $body = $this->request(
                'bibs/' . $mmsId,
                ['apikey' => $apiKey, 'expand' => 'marcxml'],
                $timeout,
                $output
            );
            if (null === $body) {
                $result['failed']++;
            } elseif (null === ($record = $this->extractRecord($body))) {
                $output->writeln('Error parsing the MARCXML record of MMS ID ' . $mmsId . '.');
                $result['failed']++;
            } else {
                $this->addHierarchyField($record, $mmsId, $name, $description, $ancestors);
                $filename = $outputDir . '/collection-' . $mmsId . '.xml';
                if (false === file_put_contents($filename, $record->ownerDocument->saveXML($record))) {
                    $output->writeln('Error writing file: ' . $filename);
                    $result['failed']++;
                } else {
                    $output->writeln(
                        '  Collection record written: ' . $filename,
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                    $result['written']++;
                }
                $memberResult = $this->downloadMembers(
                    $this->elementValue($collection['pid'] ?? null),
                    $name,
                    $ancestors,
                    $mmsId,
                    $apiKey,
                    $timeout,
                    $output,
                    $members,
                    $memberRecords
                );
                $result['failed'] += $memberResult['failed'];
            }
        }
        $childAncestors = '' === $mmsId ? $ancestors : array_merge($ancestors, [$collection]);
        foreach ($this->getElementList($collection['collections'] ?? [], 'collection') as $child) {
            $childResult = $this->downloadCollection(
                $child,
                $childAncestors,
                $outputDir,
                $apiKey,
                $timeout,
                $output,
                $members,
                $memberRecords
            );
            $result['written'] += $childResult['written'];
            $result['skipped'] += $childResult['skipped'];
            $result['failed'] += $childResult['failed'];
        }
        return $result;
    }

    /**
     * Download the records of all member titles of a collection.
     *
     * The member list is retrieved in pages of up to 100 entries via
     * GET /bibs/collections/{pid}/bibs. Each member record is then fetched individually
     * with expand=marcxml, since the member list does not contain the full MARCXML.
     *
     * @param string          $collectionPid   PID of the collection (for member list URL)
     * @param string          $name            Name of the collection
     * @param array           $ancestors       Parent collections (arrays with mms_id
     *                                         and name)
     * @param string          $apiKey          Alma API key
     * @param int             $timeout         Timeout in seconds
     * @param OutputInterface $output          Output object
     * @param array           $members         Member slots by MMS ID (passed by
     *                                         reference)
     * @param array           $memberRecords   MARCXML of the member records by MMS ID
     *                                         (passed by reference)
     *
     * @return array{failed: int} Counts
     */
    protected function downloadMembers(
        string $collectionPid,
        string $name,
        array $ancestors,
        string $mmsId,
        string $apiKey,
        int $timeout,
        OutputInterface $output,
        array &$members,
        array &$memberRecords
    ): array {
        $result = ['failed' => 0];
        $topCollection = $ancestors[0] ?? null;
        $topId = null === $topCollection
            ? $mmsId : $this->elementValue($topCollection['mms_id'] ?? null);
        $topTitle = null === $topCollection
            ? $name : $this->elementValue($topCollection['name'] ?? null);

        $limit = 100;
        $offset = 0;
        $total = 0;
        $processed = 0;
        $output->writeln('  Fetching members of "' . $name . '"...');
        $startTime = time();
        do {
            $body = $this->request(
                'bibs/collections/' . $collectionPid . '/bibs',
                ['apikey' => $apiKey, 'limit' => $limit, 'offset' => $offset],
                $timeout,
                $output
            );
            if (null === $body) {
                $result['failed']++;
                break;
            }
            $data = $this->parseResponse($body);
            if (null === $data) {
                $output->writeln(
                    'Error parsing the member list of collection PID '
                    . $collectionPid . '.'
                );
                $result['failed']++;
                break;
            }
            $total = (int)(
                $data['@attributes']['total_record_count']
                ?? $data['total_record_count'] ?? 0
            );
            $bibs = $this->getElementList($data, 'bib');
            $count = count($bibs);
            foreach ($bibs as $bib) {
                $memberId = $this->elementValue($bib['mms_id'] ?? null);
                if ('' === $memberId) {
                    continue;
                }
                if (!isset($memberRecords[$memberId])) {
                    $memberBody = $this->request(
                        'bibs/' . $memberId,
                        ['apikey' => $apiKey, 'expand' => 'marcxml'],
                        $timeout,
                        $output
                    );
                    if (null === $memberBody) {
                        $result['failed']++;
                        continue;
                    }
                    $memberRecord = $this->extractRecord($memberBody);
                    if (null === $memberRecord) {
                        $output->writeln(
                            'Error parsing the MARCXML record of MMS ID ' . $memberId . '.'
                        );
                        $result['failed']++;
                        continue;
                    }
                    $memberRecords[$memberId]
                        = $memberRecord->ownerDocument->saveXML($memberRecord);
                }
                $members[$memberId][] = [
                    'parentId' => $mmsId,
                    'parentTitle' => $name,
                    'topId' => $topId,
                    'topTitle' => $topTitle,
                ];
                $processed++;
            }
            // Advance by the number of bibs in this page, not by the number of
            // successfully processed members, to avoid re-fetching entries.
            $offset += $count;
            $output->writeln(
                '    ' . $name . ': ' . $processed . '/' . ($total ?: 'unknown')
                . ' member(s) fetched in ' . (time() - $startTime) . 's.'
            );
        } while ($count > 0 && ($offset < $total || (0 === $total && $count === $limit)));
        return $result;
    }

    /**
     * Write the member records with their hierarchy fields to files.
     *
     * Each member is written to a file collection-member-<mms_id>.xml and gets one
     * MARC 996 data field per collection it belongs to, so that records which are
     * members of several collections keep all their collection references.
     *
     * Members whose slots have not changed since the last write (tracked in
     * $written) are skipped, so the method can be called repeatedly while the
     * download is still running.
     *
     * @param array           $members       Member slots by MMS ID
     * @param array           $memberRecords MARCXML of the member records by MMS ID
     * @param array           $written       Slots last written by MMS ID (passed by
     *                                       reference)
     * @param string          $outputDir     Output directory
     * @param OutputInterface $output        Output object
     *
     * @return array{written: int, failed: int} Counts
     */
    protected function writeMembers(
        array $members,
        array $memberRecords,
        array &$written,
        string $outputDir,
        OutputInterface $output
    ): array {
        $result = ['written' => 0, 'failed' => 0];
        foreach ($members as $memberId => $slots) {
            $slotCount = count($slots);
            if (($written[$memberId] ?? 0) === $slotCount) {
                // No new slots since the last write; keep the existing file.
                continue;
            }
            $xml = $memberRecords[$memberId] ?? null;
            if (null === $xml) {
                $result['failed']++;
                continue;
            }
            $dom = new DOMDocument();
            if (!@$dom->loadXML($xml)) {
                $result['failed']++;
                continue;
            }
            $record = $dom->documentElement;
            foreach ($slots as $slot) {
                $this->addMemberHierarchyField(
                    $record,
                    $slot['parentId'],
                    $slot['parentTitle'],
                    $slot['topId'],
                    $slot['topTitle']
                );
            }
            $filename = $outputDir . '/collection-member-' . $memberId . '.xml';
            if (false === file_put_contents($filename, $dom->saveXML($record))) {
                $output->writeln('Error writing file: ' . $filename);
                $result['failed']++;
            } else {
                $written[$memberId] = $slotCount;
                $result['written']++;
            }
        }
        return $result;
    }

    /**
     * Extract the MARCXML record element from a bib response.
     *
     * @param string $body Response body
     *
     * @return ?DOMNode The record element, or null if not found
     */
    protected function extractRecord(string $body): ?DOMNode
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($body)) {
            return null;
        }
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[local-name() = "record"]');
        if (false === $nodes || 0 === $nodes->length) {
            return null;
        }
        return $nodes->item(0);
    }

    /**
     * Add a MARC 520 summary field with the collection description to a record.
     *
     * @param DOMNode $record      MARCXML record element
     * @param string  $description Description of the collection
     *
     * @return void
     */
    protected function addSummaryField(DOMNode $record, string $description): void
    {
        $datafield = $record->ownerDocument->createElement('datafield');
        $datafield->setAttribute('tag', '520');
        $datafield->setAttribute('ind1', ' ');
        $datafield->setAttribute('ind2', ' ');
        $this->addSubfield($datafield, 'a', $description);
        $record->appendChild($datafield);
    }

    /**
     * Add a MARC 996 data field with the VuFind hierarchy fields to a record.
     *
     * @param DOMNode $record      MARCXML record element
     * @param string  $mmsId       MMS ID of the collection
     * @param string  $name        Name of the collection
     * @param string  $description Description of the collection
     * @param array   $ancestors   Parent collections (arrays with mms_id and name)
     *
     * @return void
     */
    protected function addHierarchyField(
        DOMNode $record,
        string $mmsId,
        string $name,
        string $description,
        array $ancestors
    ): void {
        if ('' !== $description) {
            $this->addSummaryField($record, $description);
        }

        $isTop = empty($ancestors);
        $topCollection = $isTop ? null : $ancestors[0];
        $parentCollection = $isTop ? null : end($ancestors);
        $topId = $isTop ? $mmsId
            : $this->elementValue($topCollection['mms_id'] ?? null);
        $topTitle = $isTop ? $name
            : $this->elementValue($topCollection['name'] ?? null);
        $parentId = $isTop ? null
            : $this->elementValue($parentCollection['mms_id'] ?? null);
        $parentTitle = $isTop ? null
            : $this->elementValue($parentCollection['name'] ?? null);

        $datafield = $record->ownerDocument->createElement('datafield');
        $datafield->setAttribute('tag', '996');
        $datafield->setAttribute('ind1', ' ');
        $datafield->setAttribute('ind2', ' ');
        $this->addSubfield($datafield, 'a', $mmsId);
        $this->addSubfield($datafield, 'b', $name);
        if (null !== $parentId) {
            $this->addSubfield($datafield, 'c', $parentId);
        }
        $this->addSubfield($datafield, 'd', $topId);
        $this->addSubfield($datafield, 'e', $name . '{{{_ID_}}}' . $mmsId);
        if ('' !== $description) {
            $this->addSubfield($datafield, 'f', $description);
        }
        if (null !== $parentId) {
            $this->addSubfield($datafield, 'g', $parentTitle);
        }
        $this->addSubfield($datafield, 'h', $topTitle);
        $record->appendChild($datafield);
    }

    /**
     * Add a MARC 996 data field with the VuFind hierarchy fields to a member record.
     *
     * @param DOMNode $record        MARCXML record element
     * @param string  $parentId      MMS ID of the containing collection
     * @param string  $parentTitle   Title of the containing collection
     * @param string  $topId         MMS ID of the top-level collection
     * @param string  $topTitle      Title of the top-level collection
     *
     * @return void
     */
    protected function addMemberHierarchyField(
        DOMNode $record,
        string $parentId,
        string $parentTitle,
        string $topId,
        string $topTitle
    ): void {
        $datafield = $record->ownerDocument->createElement('datafield');
        $datafield->setAttribute('tag', '996');
        $datafield->setAttribute('ind1', ' ');
        $datafield->setAttribute('ind2', ' ');
        $this->addSubfield($datafield, 'c', $parentId);
        $this->addSubfield($datafield, 'd', $topId);
        $this->addSubfield($datafield, 'g', $parentTitle);
        $this->addSubfield($datafield, 'h', $topTitle);
        $record->appendChild($datafield);
    }

    /**
     * Add a subfield to a MARC data field.
     *
     * @param DOMNode $datafield Data field element
     * @param string  $code      Subfield code
     * @param string  $value     Subfield value
     *
     * @return void
     */
    protected function addSubfield(DOMNode $datafield, string $code, string $value): void
    {
        $subfield = $datafield->ownerDocument->createElement('subfield');
        $subfield->setAttribute('code', $code);
        $subfield->appendChild(
            $datafield->ownerDocument->createTextNode($value)
        );
        $datafield->appendChild($subfield);
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
        // Suppress parser warnings: error responses may contain non-XML content
        // (e.g. an HTML error page which echoes the request URL).
        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            return null;
        }
        $json = @json_encode($xml);
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
     * Remove the Alma API key from a string so that it cannot leak into logs.
     *
     * Error responses may echo the request URL including the apikey query
     * parameter; such text is shown in the debug output and error messages.
     *
     * @param string $text Text to redact
     *
     * @return string Redacted text
     */
    protected function redactApiKey(string $text): string
    {
        $apiKey = $this->almaConfig['Catalog']['apiKey'] ?? '';
        if ('' !== $apiKey) {
            $text = str_replace($apiKey, '***', $text);
        }
        return $text;
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
