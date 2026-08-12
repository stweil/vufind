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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use VuFindHttp\HttpService;
use VuFindConsole\Command\Util\AlmaCollectionsCommand;
use VuFindTest\Feature\FixtureTrait;

use function array_fill;
use function file_get_contents;
use function glob;
use function is_dir;
use function str_repeat;
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
     * Build a MARC record XML string.
     *
     * @param string $mmsId   MMS ID
     * @param string $title   Title
     *
     * @return string
     */
    protected function makeBibRecord(string $mmsId, string $title): string
    {
        return '<bib><mms_id>' . $mmsId
            . '</mms_id><record><leader>00000nam a2200000 a 4500</leader>'
            . '<controlfield tag="001">' . $mmsId . '</controlfield>'
            . '<datafield tag="245" ind1="0" ind2="0">'
            . '<subfield code="a">' . $title . '</subfield></datafield>'
            . '</record></bib>';
    }

    /**
     * Build a member list XML string.
     *
     * @param array|string $mmsIds List of member MMS IDs or single ID
     * @param int|null     $total  Total record count (defaults to count($ids))
     *
     * @return string
     */
    protected function makeBibList($mmsIds, ?int $total = null): string
    {
        $ids = (array)$mmsIds;
        $bibs = '';
        foreach ($ids as $id) {
            $bibs .= '<bib><mms_id>' . $id . '</mms_id></bib>';
        }
        $count = $total ?? count($ids);
        return '<bibs total_record_count="' . $count . '">' . $bibs . '</bibs>';
    }

    /**
     * Build a collection block (or a hierarchy of blocks).
     *
     * The function builds <collection> elements where children are nested
     * via <collections> tags exactly like the real Alma API responses.
     *
     * @param string $pid
     * @param string $mmsId
     * @param string $name
     * @param string $description
     * @param array  $children Arrays of [pid, mmsId, name, description]
     *
     * @return string
     */
    protected function makeCollectionBlock(
        string $pid,
        string $mmsId,
        string $name,
        string $description = 'Test collection',
        array $children = []
    ): string {
        $xml = '<collection>'
            . '<pid>' . $pid . '</pid>'
            . '<mms_id>' . $mmsId . '</mms_id>'
            . '<name>' . $name . '</name>'
            . '<description>' . $description . '</description>';
        if ($children !== []) {
            $xml .= '<collections>';
            foreach ($children as $ch) {
                $xml .= $this->makeCollectionBlock(...$ch);
            }
            $xml .= '</collections>';
        }
        $xml .= '</collection>';
        return $xml;
    }

    /**
     * Build a complete collections XML document from a nested tree.
     *
     * @param string $pid
     * @param string $mmsId
     * @param string $name
     * @param string $description
     * @param array  $children
     *
     * @return string
     */
    protected function makeCollectionsXml(
        string $pid,
        string $mmsId,
        string $name,
        string $description = 'Test collection',
        array $children = []
    ): string {
        return '<collections>'
            . $this->makeCollectionBlock($pid, $mmsId, $name, $description, $children)
            . '</collections>';
    }

    /**
     * Test downloading all collections with member records.
     *
     * @return void
     */
    public function testDownloadAllCollections(): void
    {
        $httpService = $this->getHttpService(
            // 0: collections.xml
            $this->getFixture('alma/collections.xml', 'VuFindConsole'),
            // 1: parent collection MARCXML
            $this->getFixture('alma/bib-9919814834502561.xml', 'VuFindConsole'),
            // 2: parent member list
            $this->makeBibList(['9912345678902561', '99198765432102561']),
            // 3: member 1 MARCXML
            $this->makeBibRecord('9912345678902561', 'Member Record One'),
            // 4: member 2 MARCXML
            $this->makeBibRecord('99198765432102561', 'Member Record Two'),
            // 5: child collection MARCXML
            $this->getFixture('alma/bib-9919814836202561.xml', 'VuFindConsole'),
            // 6: child member list (member 2 is also in child)
            $this->makeBibList('99198765432102561')
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--output' => $this->outputDir]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            '2 collection record(s) and 2 member record(s) written',
            $commandTester->getDisplay()
        );

        // Collection records
        $parentFile = $this->outputDir . '/collection-9919814834502561.xml';
        $childFile = $this->outputDir . '/collection-9919814836202561.xml';
        $this->assertFileExists($parentFile);
        $this->assertFileExists($childFile);

        // Member files
        $member1File = $this->outputDir . '/collection-member-9912345678902561.xml';
        $member2File = $this->outputDir . '/collection-member-99198765432102561.xml';
        $this->assertFileExists($member1File);
        $this->assertFileExists($member2File);

        // Top-level collection: no parent, top ID points to itself, includes h subfield.
        $parent996 = $this->getHierarchyField(file_get_contents($parentFile));
        $this->assertSame(
            ['a' => '9919814834502561', 'b' => 'Nachhaltigkeit 2023-2024',
             'd' => '9919814834502561',
             'e' => 'Nachhaltigkeit 2023-2024{{{_ID_}}}9919814834502561',
             'f' => 'Test collection',
             'h' => 'Nachhaltigkeit 2023-2024'],
            $parent996
        );
        $this->assertArrayNotHasKey('c', $parent996);
        $this->assertArrayNotHasKey('g', $parent996);

        // The collection description is also stored in a MARC 520 summary field.
        $this->assertSame(
            ['a' => 'Test collection'],
            $this->getSummaryField(file_get_contents($parentFile))
        );

        // Sub-collection: parent and top IDs point to parent, includes g/h.
        $child996 = $this->getHierarchyField(file_get_contents($childFile));
        $this->assertSame(
            ['a' => '9919814836202561', 'b' => 'Sub-Collection',
             'c' => '9919814834502561',
             'd' => '9919814834502561',
             'e' => 'Sub-Collection{{{_ID_}}}9919814836202561',
             'f' => 'Child collection',
             'g' => 'Nachhaltigkeit 2023-2024',
             'h' => 'Nachhaltigkeit 2023-2024'],
            $child996
        );

        // Member 1: only in parent collection => single 996 with all fields except a/b/e/f.
        $fields1 = $this->getHierarchyFields(file_get_contents($member1File));
        $this->assertCount(1, $fields1);
        $this->assertSame(
            ['c' => '9919814834502561', 'd' => '9919814834502561',
             'g' => 'Nachhaltigkeit 2023-2024', 'h' => 'Nachhaltigkeit 2023-2024'],
            $fields1[0]
        );

        // Member 2: in both parent AND child => two 996 fields.
        $fields2 = $this->getHierarchyFields(file_get_contents($member2File));
        $this->assertCount(2, $fields2);
        // First field: from parent collection
        $this->assertSame(
            ['c' => '9919814834502561', 'd' => '9919814834502561',
             'g' => 'Nachhaltigkeit 2023-2024', 'h' => 'Nachhaltigkeit 2023-2024'],
            $fields2[0]
        );
        // Second field: from child collection (parent is child, top is parent)
        $this->assertSame(
            ['c' => '9919814836202561', 'd' => '9919814834502561',
             'g' => 'Sub-Collection', 'h' => 'Nachhaltigkeit 2023-2024'],
            $fields2[1]
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
            // 0: collections.xml
            $this->getFixture('alma/collections.xml', 'VuFindConsole'),
            // 1: child collection MARCXML
            $this->getFixture('alma/bib-9919814836202561.xml', 'VuFindConsole'),
            // 2: empty member list for the selected child
            $this->makeBibList([])
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute([
            '--output' => $this->outputDir,
            '--pid' => '81368014540002561',
        ]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertFileExists($this->outputDir . '/collection-9919814836202561.xml');
        $this->assertFileDoesNotExist($this->outputDir . '/collection-9919814834502561.xml');
        // No member file should be created (empty member list).
        $this->assertFileDoesNotExist($this->outputDir . '/collection-member-9919814836202561.xml');
        $this->assertCount(0, glob($this->outputDir . '/collection-member-*.xml'));

        // A selected sub-collection is treated as the top of its hierarchy.
        $field = $this->getHierarchyField(
            file_get_contents($this->outputDir . '/collection-9919814836202561.xml')
        );
        $this->assertArrayNotHasKey('c', $field);
        $this->assertArrayNotHasKey('g', $field);
        // But h is always present (is its own name).
        $this->assertSame('Sub-Collection', $field['h']);
        $this->assertSame('9919814836202561', $field['d']);
    }

    /**
     * Test that a collection without a description gets no 996f/520 field.
     *
     * @return void
     */
    public function testDownloadWithoutDescription(): void
    {
        $collections = $this->makeCollectionsXml(
            '81368013690002561',
            '9919814834502561',
            'Collection without description',
            ''  // empty description
        );
        $httpService = $this->getHttpService(
            $collections,
            // collection MARCXML
            $this->getFixture('alma/bib-9919814834502561.xml', 'VuFindConsole'),
            // empty member list
            $this->makeBibList([])
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
     * Test a three-level hierarchy to verify the ancestors fix.
     *
     * Without the fix (using array + instead of array_merge), level-2 sub-collections
     * would get the grandparent as parent instead of the actual parent.
     *
     * @return void
     */
    /**
     * Test a three-level hierarchy to verify the ancestors fix.
     *
     * Without the fix (using array + instead of array_merge), level-2 sub-collections
     * would get the grandparent as parent instead of the actual parent.
     *
     * @return void
     */
    public function testThreeLevelHierarchy(): void
    {
        // Inline XML - direkt und unverfälscht
        $xml = '<collections total_record_count="1"><collection><pid>level0</pid>'
            . '<mms_id>9900000000002561</mms_id><name>Level 0</name>'
            . '<description>Desc 0</description>'
            . '<collections><collection><pid>level1</pid><mms_id>9900000000012561</mms_id>'
            . '<name>Level 1</name><description>Desc 1</description>'
            . '<collections><collection><pid>level2</pid>'
            . '<mms_id>9900000000022561</mms_id><name>Level 2</name>'
            . '<description>Desc 2</description></collection></collections>'
            . '</collection></collections></collection></collections>';
        $parentBib = $this->makeBibRecord('9900000000002561', 'Level 0 Title');
        $childBib = $this->makeBibRecord('9900000000012561', 'Level 1 Title');
        $grandchildBib = $this->makeBibRecord('9900000000022561', 'Level 2 Title');

        $httpService = $this->getHttpService(
            $xml,                // 0: collections
            $parentBib,          // 1: level 0
            $this->makeBibList([]),   // 2: level 0 members
            $childBib,           // 3: level 1
            $this->makeBibList([]),   // 4: level 1 members
            $grandchildBib,      // 5: level 2
            $this->makeBibList([])    // 6: level 2 members
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--output' => $this->outputDir]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('3 collection record(s) and 0 member record(s) written', $commandTester->getDisplay());

        // Level 0 (top): no c, g; h = own name.
        $f0 = $this->getHierarchyField(
            file_get_contents($this->outputDir . '/collection-9900000000002561.xml')
        );
        $this->assertArrayNotHasKey('c', $f0);
        $this->assertArrayNotHasKey('g', $f0);
        $this->assertSame('Level 0', $f0['h']);

        // Level 1: parent = level 0. c/g = Level 0, d/h = Level 0 (top).
        $f1 = $this->getHierarchyField(
            file_get_contents($this->outputDir . '/collection-9900000000012561.xml')
        );
        $this->assertSame('9900000000002561', $f1['c']);
        $this->assertSame('Level 0', $f1['g']);
        $this->assertSame('9900000000002561', $f1['d']);
        $this->assertSame('Level 0', $f1['h']);

        // Level 2: parent = level 1, top = level 0.
        $f2 = $this->getHierarchyField(
            file_get_contents($this->outputDir . '/collection-9900000000022561.xml')
        );
        $this->assertSame('9900000000012561', $f2['c']);
        $this->assertSame('Level 1', $f2['g']);
        $this->assertSame('9900000000002561', $f2['d']);
        $this->assertSame('Level 0', $f2['h']);
    }

    /**
     * Test pagination with a large member list: two pages.
     *
     * @return void
     */
    public function testMemberPagination(): void
    {
        // Page 1: 3 members (limit 100), total 3 — fits in one page but tests loop logic.
        $page1 = $this->makeBibList([
            '9990000000012561', '9990000000022561', '9990000000032561'
        ]);

        $members = [
            '9990000000012561' => $this->makeBibRecord(
                '9990000000012561', 'Paginated Member 1'
            ),
            '9990000000022561' => $this->makeBibRecord(
                '9990000000022561', 'Paginated Member 2'
            ),
            '9990000000032561' => $this->makeBibRecord(
                '9990000000032561', 'Paginated Member 3'
            ),
        ];

        $collectionsXml = $this->makeCollectionsXml(
            'test1', '9990000000002561', 'Pagination Test Collection', 'Paging desc'
        );

        $httpService = $this->getHttpService(
            $collectionsXml,         // 0: collections
            $this->makeBibRecord(    // 1: collection record
                '9990000000002561', 'Pagination Test Collection'
            ),
            $page1,                  // 2: member list
            $members['9990000000012561'],
            $members['9990000000022561'],
            $members['9990000000032561']
        );
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--output' => $this->outputDir]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString(
            '1 collection record(s) and 3 member record(s) written',
            $display
        );

        // Verify all three member files exist with correct 996.
        foreach ([1, 2, 3] as $i) {
            $file = $this->outputDir . '/collection-member-99900000000' . $i . '2561.xml';
            $this->assertFileExists($file, 'Member ' . $i);
            $fields = $this->getHierarchyFields(file_get_contents($file));
            $this->assertCount(1, $fields);
            $this->assertSame(
                ['c' => '9990000000002561', 'd' => '9990000000002561',
                 'g' => 'Pagination Test Collection',
                 'h' => 'Pagination Test Collection'],
                $fields[0]
            );
        }
    }

    /**
     * Test pagination spanning two pages: 150 members with limit=100.
     *
     * Page 1 returns 100 bibs, page 2 returns 50 bibs.
     * We generate member records for all 150 to verify loop termination.
     *
     * @return void
     */
    public function testMemberPaginationMultiplePages(): void
    {
        $page1Bibs = [];
        $page2Bibs = [];
        $memberRecords = [];
        for ($i = 1; $i <= 100; $i++) {
            $id = '99800000000' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . '2561';
            $page1Bibs[] = $id;
            $memberRecords[$id] = $this->makeBibRecord($id, 'Page 1 Member ' . $i);
        }
        for ($i = 1; $i <= 50; $i++) {
            $id = '99800000001' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . '2561';
            $page2Bibs[] = $id;
            $memberRecords[$id] = $this->makeBibRecord($id, 'Page 2 Member ' . $i);
        }

        $collectionsXml = $this->makeCollectionsXml(
            'multi', '998000000000002561', 'Multi Page Collection', 'Multi paging'
        );
        $collectionBib = $this->makeBibRecord(
            '998000000000002561', 'Multi Page Collection'
        );
        $responses = [];
        $responses[] = $collectionsXml;
        $responses[] = $collectionBib;
        $responses[] = $this->makeBibList($page1Bibs, 150);
        foreach ($page1Bibs as $id) {
            $responses[] = $memberRecords[$id];
        }
        $responses[] = $this->makeBibList($page2Bibs);
        foreach ($page2Bibs as $id) {
            $responses[] = $memberRecords[$id];
        }

        $httpService = $this->getHttpService(...$responses);
        $commandTester = new CommandTester($this->getCommand($httpService));
        $commandTester->execute(['--output' => $this->outputDir]);
        $this->assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString(
            '1 collection record(s) and 150 member record(s) written',
            $display
        );

        // Check a member from each page.
        // Page 1, ID 100: '99800000000' . '100' . '2561' = '998000000001002561'
        $member1 = $this->outputDir . '/collection-member-998000000001002561.xml';
        $this->assertFileExists($member1);
        $fields1 = $this->getHierarchyFields(file_get_contents($member1));
        $this->assertCount(1, $fields1);
        $this->assertSame(
            ['c' => '998000000000002561', 'd' => '998000000000002561',
             'g' => 'Multi Page Collection', 'h' => 'Multi Page Collection'],
            $fields1[0]
        );

        // Page 2, ID 50: '99800000001' . '50' . '2561' = '99800000001502561'
        $member2 = $this->outputDir . '/collection-member-99800000001502561.xml';
        $this->assertFileExists($member2);
        $fields2 = $this->getHierarchyFields(file_get_contents($member2));
        $this->assertCount(1, $fields2);
        $this->assertSame(
            ['c' => '998000000000002561', 'd' => '998000000000002561',
             'g' => 'Multi Page Collection', 'h' => 'Multi Page Collection'],
            $fields2[0]
        );
    }

    /**
     * Test that a transient HTTP 503 error is retried and that the API key is
     * redacted from the debug output.
     *
     * @return void
     */
    public function testRetryOnHttp503(): void
    {
        $collections = $this->makeCollectionsXml(
            'retry1', '9991111111112561', 'Retry Collection', 'Retry desc'
        );
        $adapter = new TestAdapter();
        $adapter->setResponse([
            // 0: first attempt fails with a 503 HTML error page
            $this->makeErrorResponse(503, 'Service Unavailable'),
            // 1: retry succeeds
            "HTTP/1.1 200 OK\r\nContent-Type: application/xml\r\n\r\n" . $collections,
            // 2: collection MARCXML
            "HTTP/1.1 200 OK\r\nContent-Type: application/xml\r\n\r\n"
                . $this->makeBibRecord('9991111111112561', 'Retry Collection'),
            // 3: empty member list
            "HTTP/1.1 200 OK\r\nContent-Type: application/xml\r\n\r\n"
                . $this->makeBibList([]),
        ]);
        $service = new HttpService();
        $service->setDefaultAdapter($adapter);
        $commandTester = new CommandTester(
            $this->getCommand($service, ['http_retries' => 1, 'http_retry_sleep' => 0])
        );
        $commandTester->execute(
            ['--output' => $this->outputDir],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertFileExists($this->outputDir . '/collection-9991111111112561.xml');
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString(
            'Error accessing the Alma API: HTTP 503; retrying',
            $display
        );
        // The error page echoes the request URL with the apikey; it must not leak.
        $this->assertStringNotContainsString('apikey=secret', $display);
        $this->assertStringContainsString('apikey=***', $display);
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
     * Build an HTTP error response with an HTML body that echoes the request URL
     * (like an Alma proxy error page).
     *
     * @param int    $status HTTP status code
     * @param string $reason Reason phrase
     *
     * @return string
     */
    protected function makeErrorResponse(int $status, string $reason): string
    {
        $body = '<html><body>' . $reason
            . ' at /ws/v1/bibs/9991111111112561?apikey=secret&expand=marcxml'
            . '</body></html>';
        return 'HTTP/1.1 ' . $status . ' ' . $reason
            . "\r\nContent-Type: text/html\r\n\r\n" . $body;
    }

    /**
     * Build a command object.
     *
     * @param HttpService $httpService  HTTP service
     * @param array       $catalogExtra Additional [Catalog] settings
     *
     * @return AlmaCollectionsCommand
     */
    protected function getCommand(HttpService $httpService, array $catalogExtra = []): AlmaCollectionsCommand
    {
        return new AlmaCollectionsCommand(
            [
                'Catalog' => [
                    'apiBaseUrl' => 'https://example.com/almaws/v1',
                    'apiKey' => 'secret',
                ] + $catalogExtra,
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
     * Extract the hierarchy values of the *first* MARC 996 field from a record.
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

    /**
     * Extract all MARC 996 fields from a record.
     *
     * @param string $xml MARCXML record
     *
     * @return array
     */
    protected function getHierarchyFields(string $xml): array
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//datafield[@tag="996"]');
        $this->assertNotFalse($nodes);
        $result = [];
        foreach ($nodes as $datafield) {
            $field = [];
            foreach ($datafield->getElementsByTagName('subfield') as $subfield) {
                $field[$subfield->getAttribute('code')] = $subfield->textContent;
            }
            $result[] = $field;
        }
        return $result;
    }
}
