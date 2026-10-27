<?php
/**
 * Keyword scope of SEO URLs (docs/developer/seo/keyword-scope.md):
 *  - a keyword is unique per store AND language, so one product may use the same keyword in every language;
 *  - a nested category's keyword only has to be unique among its siblings: clothes/mens and shoes/mens coexist;
 *  - the root level (products, pages, brands, top-level categories) stays one shared namespace.
 *
 * Exercises the admin check (ModelMintcartSeoUrl::keywordTaken) and the incoming-URL decoder
 * (ControllerStartupSeoUrl) against an in-memory stand-in for the two tables they read
 * (`seo_url`, `category`); no MySQL.
 *
 * Run: php tests/mintcart/seo/keyword-scope-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DB_PREFIX', 'oc_');

require __DIR__ . '/../../../upload/system/engine/model.php';
require __DIR__ . '/../../../upload/system/engine/controller.php';
require __DIR__ . '/../../../upload/system/helper/utf8.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/slugger.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/systemroutes.php';
require __DIR__ . '/../../../upload/admin/model/mintcart/seo_url.php';
require __DIR__ . '/../../../upload/catalog/controller/startup/seo_url.php';

class StubRegistry
{
    private $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }
}

class StubResult
{
    public $rows = [];
    public $row = [];
    public $num_rows = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->row = $rows[0] ?? [];
        $this->num_rows = count($rows);
    }
}

/** Understands exactly the SELECTs the two classes issue. */
class StubDb
{
    public $seo = [];
    public $categories = [];

    public function escape($value)
    {
        return addslashes((string)$value);
    }

    public function query($sql)
    {
        $sql = str_replace('`', '', $sql);

        if (preg_match("/FROM oc_seo_url WHERE keyword = '([^']*)' AND store_id = '(\d+)'(?: AND language_id = '(\d+)')?(?: AND query != '([^']*)')?/", $sql, $m)) {
            $rows = [];

            foreach ($this->seo as $i => $row) {
                if ($row['keyword'] !== $m[1] || (int)$row['store_id'] !== (int)$m[2]) {
                    continue;
                }

                if (isset($m[3]) && $m[3] !== '' && (int)$row['language_id'] !== (int)$m[3]) {
                    continue;
                }

                if (isset($m[4]) && $m[4] !== '' && $row['query'] === $m[4]) {
                    continue;
                }

                $rows[] = $row + ['seo_url_id' => $i + 1];
            }

            return new StubResult($rows);
        }

        if (preg_match('/FROM oc_category WHERE category_id IN \(([\d,]+)\)/', $sql, $m)) {
            $ids = array_map('intval', explode(',', $m[1]));
            $rows = [];

            foreach ($ids as $id) {
                if (isset($this->categories[$id])) {
                    $rows[] = ['category_id' => $id, 'parent_id' => $this->categories[$id]];
                }
            }

            return new StubResult($rows);
        }

        throw new RuntimeException('Unexpected query: ' . $sql);
    }
}

class StubConfig
{
    public $values = ['config_store_id' => 0, 'config_language_id' => 1, 'config_seo_url' => 0];

    public function get($key)
    {
        return $this->values[$key] ?? null;
    }
}

class StubRequest
{
    public $get = [];
}

$failures = [];

function check($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' - expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
}

// Categories: 1 clothes, 2 shoes (top level); 11 clothes/mens, 21 shoes/mens, 12 clothes/womens; 111 clothes/mens/shirts.
$db = new StubDb();
$db->categories = [1 => 0, 2 => 0, 11 => 1, 21 => 2, 12 => 1, 111 => 11];
$db->seo = [
    ['store_id' => 0, 'language_id' => 1, 'query' => 'category_id=1', 'keyword' => 'clothes'],
    ['store_id' => 0, 'language_id' => 1, 'query' => 'category_id=2', 'keyword' => 'shoes'],
    ['store_id' => 0, 'language_id' => 1, 'query' => 'category_id=11', 'keyword' => 'mens'],
    ['store_id' => 0, 'language_id' => 1, 'query' => 'category_id=21', 'keyword' => 'mens'],
    ['store_id' => 0, 'language_id' => 1, 'query' => 'category_id=12', 'keyword' => 'womens'],
    ['store_id' => 0, 'language_id' => 1, 'query' => 'category_id=111', 'keyword' => 'shirts'],
    ['store_id' => 0, 'language_id' => 1, 'query' => 'product_id=5', 'keyword' => 'white-mug'],
    ['store_id' => 0, 'language_id' => 2, 'query' => 'product_id=5', 'keyword' => 'white-mug'], // same keyword, other language
    ['store_id' => 0, 'language_id' => 2, 'query' => 'category_id=1', 'keyword' => 'odyag'],
    ['store_id' => 0, 'language_id' => 2, 'query' => 'category_id=11', 'keyword' => 'cholovichyi'],
];

$registry = new StubRegistry();
$registry->set('db', $db);
$config = new StubConfig();
$registry->set('config', $config);

// --- Admin: keywordTaken ---

$model = new ModelMintcartSeoUrl($registry);

check(false, $model->keywordTaken('white-mug', 0, 2, 'product_id=5'), 'the same product keeps its own keyword in another language', $failures);
check(false, $model->keywordTaken('white-mug', 0, 3, 'product_id=5'), 'a keyword free in a third language', $failures);
check(true, $model->keywordTaken('white-mug', 0, 1, 'product_id=6'), 'another product cannot take it in the same language', $failures);
check(true, $model->keywordTaken('white-mug', 0, 2, 'product_id=6'), 'another product cannot take it in language 2 either', $failures);
check(true, $model->keywordTaken('mens', 0, 1, 'category_id=99', 1), 'a new child of clothes cannot take "mens" (sibling exists)', $failures);
check(false, $model->keywordTaken('mens', 0, 1, 'category_id=99', 12), 'a child of another parent may use "mens"', $failures);
check(false, $model->keywordTaken('mens', 0, 1, 'category_id=21', 2), 'a sibling saving itself is not a conflict', $failures);
check(false, $model->keywordTaken('mens', 0, 1, 'category_id=99', null), 'a root-level page may use a keyword that only nested categories have', $failures);
check(true, $model->keywordTaken('clothes', 0, 1, 'category_id=99', 0), 'a new top-level category cannot repeat a top-level keyword', $failures);
check(true, $model->keywordTaken('clothes', 0, 1, 'product_id=99', null), 'a product cannot take a top-level category keyword', $failures);
check(true, $model->keywordTaken('shirts', 0, 1, 'category_id=99', 11), 'sibling check at the third level', $failures);
check(false, $model->keywordTaken('shirts', 0, 1, 'category_id=99', 21), 'same keyword under another parent at the third level', $failures);
check('mens-2', $model->uniqueKeyword('mens', 0, 1, 'category_id=99', 1), 'uniqueKeyword adds a suffix inside the same parent', $failures);
check('mens', $model->uniqueKeyword('mens', 0, 1, 'category_id=99', 12), 'uniqueKeyword keeps the keyword under another parent', $failures);

// --- Catalog: decoding the URL ---

function decode(StubRegistry $registry, string $route): array
{
    $request = new StubRequest();
    $request->get['_route_'] = $route;
    $registry->set('request', $request);

    $url = new class {
        public function addRewrite($rewrite)
        {
        }
    };
    $registry->set('url', $url);

    (new ControllerStartupSeoUrl($registry))->index();

    return $request->get;
}

$get = decode($registry, 'clothes/mens');
check('1_11', $get['path'] ?? null, '/clothes/mens resolves to clothes > mens', $failures);

$get = decode($registry, 'shoes/mens');
check('2_21', $get['path'] ?? null, '/shoes/mens resolves to shoes > mens', $failures);

$get = decode($registry, 'clothes/mens/shirts');
check('1_11_111', $get['path'] ?? null, 'three levels', $failures);

$get = decode($registry, 'clothes/womens');
check('1_12', $get['path'] ?? null, 'an alias without a twin still works', $failures);
check('product/category', $get['route'] ?? null, 'a category URL routes to the category page', $failures);

$get = decode($registry, 'mens');
check('11', $get['path'] ?? null, 'a bare ambiguous alias falls back to the first row (old bookmarks)', $failures);

$get = decode($registry, 'white-mug');
check('5', $get['product_id'] ?? null, 'a product at the root', $failures);

$config->values['config_language_id'] = 2;
$get = decode($registry, 'odyag/cholovichyi');
check('1_11', $get['path'] ?? null, 'language 2 decodes its own keywords', $failures);

$get = decode($registry, 'white-mug');
check('5', $get['product_id'] ?? null, 'the same product keyword in language 2', $failures);

$config->values['config_language_id'] = 1;
$get = decode($registry, 'clothes/nothing-here');
check('error/not_found', $get['route'] ?? null, 'an unknown segment is a 404', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "keyword-scope-test: OK\n";
