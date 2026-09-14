<?php
/**
 * Standalone smoke test for MintCart\Compatibility\InstallManifestParser.
 *
 * No PHPUnit/Composer in the repo yet, same as store-readiness-test.php.
 * InstallManifestParser has no OpenCart dependency by design, so this runs
 * entirely against plain XML strings.
 *
 * Run: php tests/mintcart/compatibility/install-manifest-parser-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/compatibility/installmanifestparser.php';

use MintCart\Compatibility\InstallManifestParser;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$parser = new InstallManifestParser();

// 1. A minimal, realistic install.xml with one file/one operation parses
//    every top-level metadata field and the one file entry.
$xml1 = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>Example Extension</name>
    <code>example_extension</code>
    <version>1.0.0</version>
    <author>Example Author</author>
    <link>https://example.com</link>
    <file path="catalog/controller/product/product.php">
        <operation>
            <search><![CDATA[public function index() {]]></search>
            <add position="after"><![CDATA[// injected code]]></add>
        </operation>
    </file>
</modification>
XML;
$result1 = $parser->parse($xml1);
assertSame('Example Extension', $result1['name'], 'parse: name', $failures);
assertSame('example_extension', $result1['code'], 'parse: code', $failures);
assertSame('1.0.0', $result1['version'], 'parse: version', $failures);
assertSame('Example Author', $result1['author'], 'parse: author', $failures);
assertSame('https://example.com', $result1['link'], 'parse: link', $failures);
assertSame(1, count($result1['files']), 'parse: one <file> element -> one files entry', $failures);
assertSame('catalog/controller/product/product.php', $result1['files'][0]['path'], 'parse: file path', $failures);
assertSame(1, $result1['files'][0]['operation_count'], 'parse: one <operation> counted', $failures);
assertTrue(
    strpos($result1['files'][0]['content'], 'public function index()') !== false,
    'parse: <search> content captured',
    $failures
);
assertTrue(
    strpos($result1['files'][0]['content'], 'injected code') !== false,
    'parse: <add> content captured',
    $failures
);

// 2. Missing optional metadata (author/link) defaults to empty strings
//    rather than throwing or emitting null.
$xml2 = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>No Author</name>
    <code>no_author</code>
    <version>2.0.0</version>
    <file path="catalog/view/theme/default/template/product/product.tpl">
        <operation>
            <search><![CDATA[<div class="panel">]]></search>
            <add><![CDATA[<div class="panel new">]]></add>
        </operation>
        <operation>
            <search><![CDATA[foo]]></search>
            <add><![CDATA[bar]]></add>
        </operation>
    </file>
</modification>
XML;
$result2 = $parser->parse($xml2);
assertSame('', $result2['author'], 'parse: missing <author> defaults to empty string', $failures);
assertSame('', $result2['link'], 'parse: missing <link> defaults to empty string', $failures);
assertSame(2, $result2['files'][0]['operation_count'], 'parse: two <operation> elements counted for one file', $failures);

// 3. Multiple <file> elements each become their own files[] entry, in
//    document order.
$xml3 = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>Multi File</name>
    <code>multi_file</code>
    <version>1.0.0</version>
    <file path="catalog/controller/product/category.php">
        <operation><search><![CDATA[a]]></search><add><![CDATA[b]]></add></operation>
    </file>
    <file path="catalog/controller/extension/module/foo.php">
        <operation><search><![CDATA[c]]></search><add><![CDATA[d]]></add></operation>
    </file>
</modification>
XML;
$result3 = $parser->parse($xml3);
assertSame(2, count($result3['files']), 'parse: two <file> elements -> two files entries', $failures);
assertSame('catalog/controller/product/category.php', $result3['files'][0]['path'], 'parse: first file path, document order', $failures);
assertSame('catalog/controller/extension/module/foo.php', $result3['files'][1]['path'], 'parse: second file path, document order', $failures);

// 4. A leading slash on a path attribute (some older install.xml files
//    write it this way) is normalized away rather than treated as a
//    different path.
$xml4 = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>Leading Slash</name>
    <code>leading_slash</code>
    <version>1.0.0</version>
    <file path="/catalog/controller/product/product.php">
        <operation><search><![CDATA[a]]></search><add><![CDATA[b]]></add></operation>
    </file>
</modification>
XML;
$result4 = $parser->parse($xml4);
assertSame('catalog/controller/product/product.php', $result4['files'][0]['path'], 'parse: leading slash on path is normalized away', $failures);

// 5. A <modification> with no <file> elements at all returns an empty
//    files array, not an error.
$xml5 = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>No Files</name>
    <code>no_files</code>
    <version>1.0.0</version>
</modification>
XML;
$result5 = $parser->parse($xml5);
assertSame([], $result5['files'], 'parse: <modification> with no <file> elements -> empty files array', $failures);

// 6. Malformed (not well-formed) XML raises InvalidArgumentException
//    rather than a fatal error or a silently empty result.
$threw = false;
try {
    $parser->parse('<modification><file path="a.php">');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'parse: malformed XML raises InvalidArgumentException', $failures);

// 7. Well-formed XML with the wrong root element also raises
//    InvalidArgumentException, since it can't be an OCMOD install.xml.
$threw = false;
try {
    $parser->parse('<?xml version="1.0"?><not_a_modification></not_a_modification>');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'parse: wrong root element raises InvalidArgumentException', $failures);

// 8. A <file> element with no path attribute is skipped, not fatal.
$xml8 = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>No Path</name>
    <code>no_path</code>
    <version>1.0.0</version>
    <file>
        <operation><search><![CDATA[a]]></search><add><![CDATA[b]]></add></operation>
    </file>
    <file path="catalog/controller/product/product.php">
        <operation><search><![CDATA[a]]></search><add><![CDATA[b]]></add></operation>
    </file>
</modification>
XML;
$result8 = $parser->parse($xml8);
assertSame(1, count($result8['files']), 'parse: <file> with no path attribute is skipped, not fatal', $failures);
assertSame('catalog/controller/product/product.php', $result8['files'][0]['path'], 'parse: the remaining valid <file> is still parsed', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: InstallManifestParser smoke test (8 scenarios)\n";
exit(0);
