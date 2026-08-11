<?php

/**
 * Util/AlmaCollections command test.
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
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Util;

use DOMDocument;
use DOMXPath;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use VuFindHttp\HttpService;
use VuFindConsole\Command\Util\AlmaCollectionsCommand;
use VuFindTest\Feature\FixtureTrait;

use function file_get_contents;
use function glob;
use function is_dir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function rmdir;

/**
 * Util/AlmaCollections command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AlmaCollectionsCommandTest extends TestCase
{
    use FixtureTrait;

    /**
     * Output directory used by the current test.
     *
     * @var string
     */
    protected string $outputDir = '';

    /**
     * Set up tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/alma-collections-test-' . uniqid();
    }

    /**
     * Tear down tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            foreach (glob($this->outputDir . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->outputDir);
        }
    }

    /**
     * Test downloading all collections.
     *
     * @return void
     */
    public function testDownloadAllCollections(): void
    {
        $httpService = $this->getHttpService(
            $this->getFixture('alma/collections.xml', 'VuFindConsole'),
            $this->getFixture('alma/bib-9919814834502561.xml', 'VuFindConsole'),
            $this->getFixture('alma/bib-9919814836202561.xml', 'VuFindConsole')
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--output' => $this->outputDir]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            '2 collection record(s) written',
            $commandTester->getDisplay()
        );

        $parentFile = $this->outputDir . '/collection-9919814834502561.xml';
        $childFile = $this->outputDir . '/collection-9919814836202561.xml';
        $this->assertFileExists($parentFile);
        $this->assertFileExists($childFile);

        // Top-level collection: no parent, top ID points to itself.
        $parent = $this->getHierarchyField(file_get_contents($parentFile));
        $this->assertSame(
            ['a' => '9919814834502561', 'b' => 'Nachhaltigkeit 2023-2024',
             'd' => '9919814834502561', 'e' => 'Nachhaltigkeit 2023-2024{{{_ID_}}}9919814834502561',
             'f' => 'Test collection'],
            $parent
        );
        $this->assertArrayNotHasKey('c', $parent);

        // The collection description is also stored in a MARC 520 summary field.
        $this->assertSame(
            ['a' => 'Test collection'],
            $this->getSummaryField(file_get_contents($parentFile))
        );

        // Sub-collection: parent and top IDs point to the parent collection.
        $child = $this->getHierarchyField(file_get_contents($childFile));
        $this->assertSame(
            ['a' => '9919814836202561', 'b' => 'Sub-Collection', 'c' => '9919814834502561',
             'd' => '9919814834502561', 'e' => 'Sub-Collection{{{_ID_}}}9919814836202561',
             'f' => 'Child collection'],
            $child
        );
        $this->assertSame(
            ['a' => 'Child collection'],
            $this->getSummaryField(file_get_contents($childFile))
        );
    }

    /**
     * Test downloading a single collection selected by PID.
     *
     * @return void
     */
    public function testDownloadSingleCollection(): void
    {
        $httpService = $this->getHttpService(
            $this->getFixture('alma/collections.xml', 'VuFindConsole'),
            $this->getFixture('alma/bib-9919814836202561.xml', 'VuFindConsole')
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute([
            '--output' => $this->outputDir,
            '--pid' => '81368014540002561',
        ]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertFileExists($this->outputDir . '/collection-9919814836202561.xml');
        $this->assertFileDoesNotExist($this->outputDir . '/collection-9919814834502561.xml');

        // A selected sub-collection is treated as the top of its hierarchy.
        $field = $this->getHierarchyField(
            file_get_contents($this->outputDir . '/collection-9919814836202561.xml')
        );
        $this->assertArrayNotHasKey('c', $field);
        $this->assertSame('9919814836202561', $field['d']);
    }

    /**
     * Test that a collection without a description gets no 996f subfield
     * and no 520 field.
     *
     * @return void
     */
    public function testDownloadWithoutDescription(): void
    {
        $collections = '<?xml version="1.0"?>'
            . '<collections total_record_count="1">'
            . '<collection><pid>81368013690002561</pid>'
            . '<mms_id>9919814834502561</mms_id>'
            . '<name>Collection without description</name>'
            . '<description/></collection>'
            . '</collections>';
        $httpService = $this->getHttpService(
            $collections,
            $this->getFixture('alma/bib-9919814834502561.xml', 'VuFindConsole')
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--output' => $this->outputDir]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $record = file_get_contents($this->outputDir . '/collection-9919814834502561.xml');
        $field = $this->getHierarchyField($record);
        $this->assertArrayNotHasKey('f', $field);
        $this->assertSame([], $this->getSummaryField($record));
    }

    /**
     * Test the default listing mode (regression test for version 1).
     *
     * @return void
     */
    public function testListCollections(): void
    {
        $httpService = $this->getHttpService(
            $this->getFixture('alma/collections.xml', 'VuFindConsole')
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute([]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Nachhaltigkeit 2023-2024',
            $commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            '2 collection(s) listed.',
            $commandTester->getDisplay()
        );
    }

    /**
     * Test that a missing collection PID yields an error.
     *
     * @return void
     */
    public function testUnknownPid(): void
    {
        $httpService = $this->getHttpService(
            $this->getFixture('alma/collections.xml', 'VuFindConsole')
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--pid' => 'nonexistent']);
        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'No collection with PID nonexistent found.',
            $commandTester->getDisplay()
        );
    }

    /**
     * Build an HTTP service that returns the given response bodies in order.
     *
     * @param string ...$bodies Response bodies
     *
     * @return HttpService
     */
    protected function getHttpService(string ...$bodies): HttpService
    {
        $adapter = new TestAdapter();
        $responses = [];
        foreach ($bodies as $body) {
            $responses[] = "HTTP/1.1 200 OK\r\nContent-Type: application/xml\r\n\r\n" . $body;
        }
        $adapter->setResponse($responses);
        $service = new HttpService();
        $service->setDefaultAdapter($adapter);
        return $service;
    }

    /**
     * Build a command object.
     *
     * @param HttpService $httpService HTTP service
     *
     * @return AlmaCollectionsCommand
     */
    protected function getCommand(HttpService $httpService): AlmaCollectionsCommand
    {
        return new AlmaCollectionsCommand(
            [
                'Catalog' => [
                    'apiBaseUrl' => 'https://example.com/almaws/v1',
                    'apiKey' => 'secret',
                ],
            ],
            $httpService
        );
    }

    /**
     * Extract the summary values of the MARC 520 field from a record.
     *
     * @param string $xml MARCXML record
     *
     * @return array
     */
    protected function getSummaryField(string $xml): array
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//datafield[@tag="520"]');
        $this->assertNotFalse($nodes);
        if (0 === $nodes->length) {
            return [];
        }
        $result = [];
        foreach ($nodes->item(0)->getElementsByTagName('subfield') as $subfield) {
            $result[$subfield->getAttribute('code')] = $subfield->textContent;
        }
        return $result;
    }

    /**
     * Extract the hierarchy values of the MARC 996 field from a record.
     *
     * @param string $xml MARCXML record
     *
     * @return array
     */
    protected function getHierarchyField(string $xml): array
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//datafield[@tag="996"]');
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length);
        $result = [];
        foreach ($nodes->item(0)->getElementsByTagName('subfield') as $subfield) {
            $result[$subfield->getAttribute('code')] = $subfield->textContent;
        }
        return $result;
    }
}
