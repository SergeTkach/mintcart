<?php
/**
 * Standalone smoke test for ModelDesignHomepage (the Homepage Builder admin
 * editor's write model, `upload/admin/model/design/homepage.php`).
 *
 * No PHPUnit/Composer and no MySQL server in this environment (same gap
 * every previous phase's test carries), so this drives the real model
 * class against an in-memory `FakeDb` double that duck-types just enough
 * of OpenCart's `\DB` (query()/escape()) to satisfy the SQL this model
 * actually issues — same approach as
 * `tests/mintcart/core/migration-manager-test.php` and
 * `tests/mintcart/core/mc-block-migrations-test.php`.
 *
 * Loads the real `upload/system/engine/model.php` (OpenCart's base `Model`
 * class) rather than re-implementing it, so this test exercises the exact
 * `$this->db`/`$this->config` proxying `ModelDesignHomepage` relies on.
 *
 * Run: php tests/mintcart/design/homepage-model-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DB_PREFIX', 'oc_');

require __DIR__ . '/../../../upload/system/engine/model.php';
require __DIR__ . '/../../../upload/admin/model/design/homepage.php';

/** @var string[] $failures */
$failures = [];

function assertSame___($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertTrue___($condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * In-memory double for OpenCart's \DB. Understands just enough of the SQL
 * shapes ModelDesignHomepage issues (INSERT ... SET / UPDATE ... SET ...
 * WHERE / DELETE FROM ... WHERE / SELECT * FROM ... WHERE / the two-table
 * mc_block + mc_block_description SELECT with LEFT JOIN / SELECT MAX(...))
 * to drive it end to end without a real MySQL connection.
 */
final class FakeDb
{
    /** @var array<int, array<string, mixed>> */
    public array $pages = [];

    /** @var array<int, array<string, mixed>> */
    public array $pageDescriptions = [];

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    /** @var array<int, array<string, mixed>> */
    public array $blockDescriptions = [];

    private int $lastId = 0;

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    public function getLastId(): int
    {
        return $this->lastId;
    }

    /** @return array<string, string> field => unescaped value */
    private function parseAssignments(string $clause): array
    {
        preg_match_all('/`(\w+)`\s*=\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|(NOW\(\)))/', $clause, $matches, PREG_SET_ORDER);

        $fields = [];

        foreach ($matches as $match) {
            $fields[$match[1]] = !empty($match[3]) ? $match[3] : stripslashes($match[2]);
        }

        return $fields;
    }

    private function matchesWhere(array $row, array $where): bool
    {
        foreach ($where as $field => $value) {
            if (!array_key_exists($field, $row) || (string) $row[$field] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    public function query(string $sql): object
    {
        $sql = trim($sql);

        // SELECT MAX(`sort_order`) AS `max_sort_order` FROM oc_mc_block WHERE `page_id` = '1'
        if (preg_match('/^SELECT MAX\(`sort_order`\).*FROM `?' . DB_PREFIX . 'mc_block`?\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $where = $this->parseAssignments($m[1]);
            $matching = array_filter($this->blocks, fn ($row) => $this->matchesWhere($row, $where));
            $max = null;

            foreach ($matching as $row) {
                $max = $max === null ? (int) $row['sort_order'] : max($max, (int) $row['sort_order']);
            }

            return $this->result([['max_sort_order' => $max]]);
        }

        // SELECT b.`block_id`, ... FROM oc_mc_block b LEFT JOIN oc_mc_block_description bd ON (...) WHERE b.`page_id` = '1' ORDER BY ...
        if (str_contains($sql, DB_PREFIX . 'mc_block b') && str_contains($sql, 'LEFT JOIN')) {
            preg_match('/WHERE\s+b\.`page_id`\s*=\s*\'(\d+)\'/', $sql, $m);
            $pageId = (int) $m[1];

            $matching = array_values(array_filter($this->blocks, fn ($row) => (int) $row['page_id'] === $pageId));

            usort($matching, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order'] ?: $a['block_id'] <=> $b['block_id']);

            preg_match('/bd\.`language_id`\s*=\s*\'(\d+)\'/', $sql, $lm);
            $languageId = (int) $lm[1];

            $rows = [];

            foreach ($matching as $row) {
                $description = null;

                foreach ($this->blockDescriptions as $bd) {
                    if ((int) $bd['block_id'] === (int) $row['block_id'] && (int) $bd['language_id'] === $languageId) {
                        $description = $bd['content_json'];
                        break;
                    }
                }

                $rows[] = [
                    'block_id' => $row['block_id'],
                    'type' => $row['type'],
                    'sort_order' => $row['sort_order'],
                    'status' => $row['status'],
                    'settings_json' => $row['settings_json'],
                    'content_json' => $description,
                ];
            }

            return $this->result($rows);
        }

        // INSERT INTO oc_mc_page SET ...
        if (preg_match('/^INSERT INTO `?' . DB_PREFIX . 'mc_page`?\s+SET\s+(.+)$/s', $sql, $m)) {
            $fields = $this->parseAssignments($m[1]);
            $id = count($this->pages) + 1;
            $fields['page_id'] = $id;
            $this->pages[] = $fields;
            $this->lastId = $id;

            return $this->result([]);
        }

        // INSERT INTO oc_mc_page_description SET ...
        if (preg_match('/^INSERT INTO `?' . DB_PREFIX . 'mc_page_description`?\s+SET\s+(.+)$/s', $sql, $m)) {
            $this->pageDescriptions[] = $this->parseAssignments($m[1]);

            return $this->result([]);
        }

        // INSERT INTO oc_mc_block SET ...
        if (preg_match('/^INSERT INTO `?' . DB_PREFIX . 'mc_block`?\s+SET\s+(.+)$/s', $sql, $m)) {
            $fields = $this->parseAssignments($m[1]);
            $id = count($this->blocks) + 1;
            $fields['block_id'] = $id;
            $this->blocks[] = $fields;
            $this->lastId = $id;

            return $this->result([]);
        }

        // INSERT INTO oc_mc_block_description SET ...
        if (preg_match('/^INSERT INTO `?' . DB_PREFIX . 'mc_block_description`?\s+SET\s+(.+)$/s', $sql, $m)) {
            $this->blockDescriptions[] = $this->parseAssignments($m[1]);

            return $this->result([]);
        }

        // UPDATE oc_mc_block SET ... WHERE ...
        if (preg_match('/^UPDATE `?' . DB_PREFIX . 'mc_block`?\s+SET\s+(.+?)\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $set = $this->parseAssignments($m[1]);
            $where = $this->parseAssignments($m[2]);

            foreach ($this->blocks as &$row) {
                if ($this->matchesWhere($row, $where)) {
                    $row = array_merge($row, $set);
                }
            }
            unset($row);

            return $this->result([]);
        }

        // SELECT * FROM oc_mc_page WHERE `code` = '...'
        if (preg_match('/^SELECT \* FROM `?' . DB_PREFIX . 'mc_page`?\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $where = $this->parseAssignments($m[1]);
            $matching = array_values(array_filter($this->pages, fn ($row) => $this->matchesWhere($row, $where)));

            return $this->result($matching);
        }

        // SELECT * FROM oc_mc_block WHERE `block_id` = '...'
        if (preg_match('/^SELECT \* FROM `?' . DB_PREFIX . 'mc_block`?\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $where = $this->parseAssignments($m[1]);
            $matching = array_values(array_filter($this->blocks, fn ($row) => $this->matchesWhere($row, $where)));

            return $this->result($matching);
        }

        // SELECT * FROM oc_mc_block_description WHERE `block_id` = '...'
        if (preg_match('/^SELECT \* FROM `?' . DB_PREFIX . 'mc_block_description`?\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $where = $this->parseAssignments($m[1]);
            $matching = array_values(array_filter($this->blockDescriptions, fn ($row) => $this->matchesWhere($row, $where)));

            return $this->result($matching);
        }

        // DELETE FROM oc_mc_block WHERE ...
        if (preg_match('/^DELETE FROM `?' . DB_PREFIX . 'mc_block`?\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $where = $this->parseAssignments($m[1]);
            $this->blocks = array_values(array_filter($this->blocks, fn ($row) => !$this->matchesWhere($row, $where)));

            return $this->result([]);
        }

        // DELETE FROM oc_mc_block_description WHERE ...
        if (preg_match('/^DELETE FROM `?' . DB_PREFIX . 'mc_block_description`?\s+WHERE\s+(.+)$/s', $sql, $m)) {
            $where = $this->parseAssignments($m[1]);
            $this->blockDescriptions = array_values(array_filter($this->blockDescriptions, fn ($row) => !$this->matchesWhere($row, $where)));

            return $this->result([]);
        }

        throw new \RuntimeException('FakeDb: unrecognized query: ' . $sql);
    }

    private function result(array $rows): object
    {
        return (object) [
            'rows' => $rows,
            'row' => $rows[0] ?? [],
            'num_rows' => count($rows),
        ];
    }
}

final class FakeConfig
{
    public function get(string $key)
    {
        return $key === 'config_language_id' ? 1 : null;
    }
}

final class FakeRegistry
{
    public function __construct(private FakeDb $db, private FakeConfig $config)
    {
    }

    public function get(string $key)
    {
        return match ($key) {
            'db' => $this->db,
            'config' => $this->config,
            default => null,
        };
    }
}

$db = new FakeDb();
$model = new ModelDesignHomepage(new FakeRegistry($db, new FakeConfig()));

// 1. No page yet.
assertSame___(null, $model->getPage('home'), 'Scenario 1: getPage() on an empty table should return null', $failures);

// 2. addPage() creates the page + its description row; getPage() finds it.
$pageId = $model->addPage('home', 'Homepage');
assertSame___(1, $pageId, 'Scenario 2: addPage() should return the new page_id', $failures);

$page = $model->getPage('home');
assertTrue___($page !== null, 'Scenario 2: getPage(\'home\') should find the page just created', $failures);
assertSame___('1', (string) $page['status'], 'Scenario 2: a new page should default to enabled (status=1)', $failures);
assertSame___(1, count($db->pageDescriptions), 'Scenario 2: addPage() should also insert one mc_page_description row', $failures);

// 3. addBlock() with settings + one language's content; getBlocks() returns it decoded.
$blockId1 = $model->addBlock($pageId, [
    'type' => 'products',
    'status' => true,
    'settings' => ['category_id' => 42, 'limit' => 4, 'sort' => 'p.viewed', 'order' => 'ASC'],
    'content' => [1 => ['title' => 'Bestsellers']],
]);

assertSame___(1, $blockId1, 'Scenario 3: addBlock() should return the new block_id', $failures);

$blocks = $model->getBlocks($pageId, 1);
assertSame___(1, count($blocks), 'Scenario 3: getBlocks() should return the one block just added', $failures);
assertSame___('products', $blocks[0]['type'], 'Scenario 3: the block\'s type should round-trip', $failures);
assertSame___(true, $blocks[0]['status'], 'Scenario 3: an enabled block should decode status as true', $failures);
assertSame___(0, $blocks[0]['sort_order'], 'Scenario 3: the first block on a page should default to sort_order 0', $failures);
assertSame___(42, $blocks[0]['settings']['category_id'], 'Scenario 3: settings_json should round-trip through json_encode/decode', $failures);
assertSame___('Bestsellers', $blocks[0]['content']['title'], 'Scenario 3: content_json for the requested language should round-trip', $failures);

// 4. A second block on the same page auto-increments sort_order (max + 1).
$blockId2 = $model->addBlock($pageId, [
    'type' => 'products',
    'status' => false,
    'settings' => ['category_id' => null, 'limit' => 8, 'sort' => 'p.date_added', 'order' => 'DESC'],
    'content' => [],
]);

$blocks = $model->getBlocks($pageId, 1);
assertSame___(2, count($blocks), 'Scenario 4: getBlocks() should now return both blocks', $failures);
assertSame___(1, $blocks[1]['sort_order'], 'Scenario 4: the second block should auto-increment sort_order to max+1 (1)', $failures);
assertSame___(false, $blocks[1]['status'], 'Scenario 4: a disabled block should decode status as false', $failures);

// 5. editBlock() replaces settings/status and re-saves descriptions (old
//    description row is deleted first, not merged).
$model->editBlock($blockId1, [
    'type' => 'products',
    'status' => false,
    'settings' => ['category_id' => null, 'limit' => 8, 'sort' => 'p.date_added', 'order' => 'DESC'],
    'content' => [1 => ['title' => 'Updated title']],
]);

$editedBlock = $model->getBlock($blockId1);
assertSame___(false, (bool) $editedBlock['status'], 'Scenario 5: editBlock() should persist the new status', $failures);
assertSame___(8, $editedBlock['settings']['limit'], 'Scenario 5: editBlock() should persist the new settings', $failures);

$descriptions = $model->getBlockDescriptions($blockId1);
assertSame___(1, count($descriptions), 'Scenario 5: editBlock() should leave exactly one description row (old one deleted first)', $failures);
assertSame___('Updated title', $descriptions[1]['title'], 'Scenario 5: the description content should reflect the edit', $failures);

// 6. updateSortOrder() only touches blocks matching both block_id AND
//    page_id (the guard against a crafted cross-page block_id).
$model->updateSortOrder($pageId, [$blockId1 => 5, $blockId2 => 3]);
$blocks = $model->getBlocks($pageId, 1);
$byId = [];
foreach ($blocks as $block) {
    $byId[$block['block_id']] = $block['sort_order'];
}
assertSame___(5, $byId[$blockId1], 'Scenario 6: updateSortOrder() should update the first block\'s sort_order', $failures);
assertSame___(3, $byId[$blockId2], 'Scenario 6: updateSortOrder() should update the second block\'s sort_order', $failures);

$model->updateSortOrder(9999, [$blockId1 => 0]);
$blocks = $model->getBlocks($pageId, 1);
$byId = [];
foreach ($blocks as $block) {
    $byId[$block['block_id']] = $block['sort_order'];
}
assertSame___(5, $byId[$blockId1], 'Scenario 6: updateSortOrder() with the wrong page_id should not touch a block belonging to a different page', $failures);

// 7. deleteBlock() removes both the block row and its description(s).
$model->deleteBlock($blockId1);
assertSame___(null, $model->getBlock($blockId1), 'Scenario 7: deleteBlock() should remove the mc_block row', $failures);
assertSame___([], $model->getBlockDescriptions($blockId1), 'Scenario 7: deleteBlock() should remove the mc_block_description row(s) too', $failures);

$blocks = $model->getBlocks($pageId, 1);
assertSame___(1, count($blocks), 'Scenario 7: getBlocks() should now return only the remaining block', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: ModelDesignHomepage smoke test (7 scenarios)\n";
exit(0);
