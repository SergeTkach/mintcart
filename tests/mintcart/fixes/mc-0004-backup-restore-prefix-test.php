<?php
/**
 * Regression test for MC-0004 (docs/fixes/registry.md): the
 * user/user_group safety-stop check in
 * ControllerToolBackup::import() (upload/admin/controller/tool/backup.php)
 * used to compare against hardcoded byte-lengths (24/30) that only happen
 * to be correct for the default 'oc_' DB_PREFIX. Any other prefix length
 * silently broke the check.
 *
 * The real method can't be exercised directly (it needs a live OpenCart
 * bootstrap: $this->db, $this->request, file handles, DB_PREFIX as an
 * actual global constant). Since DB_PREFIX can only be defined once per
 * process, this test instead reproduces the exact comparison logic - both
 * the old, buggy shape and the new, fixed shape - as small local
 * functions parameterized by prefix, and proves the fixed shape stays
 * correct across prefix lengths where the old one didn't.
 *
 * Run: php tests/mintcart/fixes/mc-0004-backup-restore-prefix-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * The pre-fix logic: hardcoded lengths that only match a 3-character
 * prefix (e.g. the stock 'oc_' default).
 */
function stopsAtUserTable_buggy(string $line, string $prefix): bool
{
    return substr($line, 0, 24) == 'TRUNCATE TABLE `' . $prefix . 'user`'
        || substr($line, 0, 30) == 'TRUNCATE TABLE `' . $prefix . 'user_group`';
}

/**
 * The fixed logic now shipped in ControllerToolBackup::import(): the
 * comparison length is derived from the needle itself, so it is correct
 * for any DB_PREFIX length.
 */
function stopsAtUserTable_fixed(string $line, string $prefix): bool
{
    $userTruncate = 'TRUNCATE TABLE `' . $prefix . 'user`';
    $userGroupTruncate = 'TRUNCATE TABLE `' . $prefix . 'user_group`';

    return substr($line, 0, strlen($userTruncate)) == $userTruncate
        || substr($line, 0, strlen($userGroupTruncate)) == $userGroupTruncate;
}

// 1. The default 'oc_' (3-char) prefix: both the old and new logic agree,
//    which is exactly why this bug shipped unnoticed - the stock install
//    never exercises the broken path.
$prefix = 'oc_';
$userLine = 'TRUNCATE TABLE `' . $prefix . 'user`;' . "\n";
$userGroupLine = 'TRUNCATE TABLE `' . $prefix . 'user_group`;' . "\n";
assertTrue(stopsAtUserTable_buggy($userLine, $prefix), 'buggy: default oc_ prefix still matches user (why this shipped unnoticed)', $failures);
assertTrue(stopsAtUserTable_fixed($userLine, $prefix), 'fixed: default oc_ prefix matches user', $failures);
assertTrue(stopsAtUserTable_fixed($userGroupLine, $prefix), 'fixed: default oc_ prefix matches user_group', $failures);

// 2. A longer, real-world custom prefix (MintCart's own installer allows
//    choosing any DB_PREFIX, same as stock OpenCart) - the buggy
//    hardcoded-length version fails to detect either safety-stop line.
$prefix = 'mintcart_';
$userLine = 'TRUNCATE TABLE `' . $prefix . 'user`;' . "\n";
$userGroupLine = 'TRUNCATE TABLE `' . $prefix . 'user_group`;' . "\n";
assertTrue(!stopsAtUserTable_buggy($userLine, $prefix), 'buggy: a 9-char prefix breaks the user-table check (reproduces MC-0004)', $failures);
assertTrue(!stopsAtUserTable_buggy($userGroupLine, $prefix), 'buggy: a 9-char prefix breaks the user_group-table check (reproduces MC-0004)', $failures);
assertTrue(stopsAtUserTable_fixed($userLine, $prefix), 'fixed: a 9-char prefix still matches user', $failures);
assertTrue(stopsAtUserTable_fixed($userGroupLine, $prefix), 'fixed: a 9-char prefix still matches user_group', $failures);

// 3. A shorter custom prefix than the default.
$prefix = 'a_';
$userLine = 'TRUNCATE TABLE `' . $prefix . 'user`;' . "\n";
assertTrue(!stopsAtUserTable_buggy($userLine, $prefix), 'buggy: a shorter prefix also breaks the check', $failures);
assertTrue(stopsAtUserTable_fixed($userLine, $prefix), 'fixed: a shorter prefix still matches', $failures);

// 4. An empty prefix (OpenCart allows installing with no table prefix at all).
$prefix = '';
$userLine = 'TRUNCATE TABLE `user`;' . "\n";
$userGroupLine = 'TRUNCATE TABLE `user_group`;' . "\n";
assertTrue(!stopsAtUserTable_buggy($userLine, $prefix), 'buggy: an empty prefix also breaks the user-table check', $failures);
assertTrue(stopsAtUserTable_fixed($userLine, $prefix), 'fixed: an empty prefix matches user', $failures);
assertTrue(stopsAtUserTable_fixed($userGroupLine, $prefix), 'fixed: an empty prefix matches user_group', $failures);

// 5. The fixed logic must not false-positive on an unrelated table with a
//    prefix that happens to be a leading substring of "user" (e.g. a
//    table literally named "usergroup_settings" some third-party
//    extension might add) - it must match on the exact needle, not just
//    any leading text.
$prefix = 'oc_';
$unrelatedLine = 'TRUNCATE TABLE `' . $prefix . 'user_settings`;' . "\n";
assertTrue(!stopsAtUserTable_fixed($unrelatedLine, $prefix), 'fixed: does not false-positive on a table merely starting with "user"', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: MC-0004 backup/restore prefix-length smoke test (5 scenarios)\n";
exit(0);
