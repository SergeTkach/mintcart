<?php
/**
 * Standalone smoke test for MintCart\Onboarding\WizardProgress.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. WizardProgress has no OpenCart dependency by design, so this
 * runs entirely against plain arrays.
 *
 * Run: php tests/mintcart/onboarding/wizard-progress-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/onboarding/wizardprogress.php';

use MintCart\Onboarding\WizardProgress;

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

// 1. Fresh progress (nothing completed) starts at the first implemented step.
$progress = new WizardProgress([]);
assertSame('welcome', $progress->currentStep(), 'fresh: currentStep is welcome', $failures);
assertTrue(!$progress->isCompleted('welcome'), 'fresh: welcome not completed', $failures);
assertTrue(!$progress->isReady(), 'fresh: not ready', $failures);

// 2. Completing welcome advances to store.
$afterWelcome = new WizardProgress($progress->withCompleted('welcome'));
assertSame('store', $afterWelcome->currentStep(), 'after welcome: currentStep is store', $failures);
assertTrue($afterWelcome->isCompleted('welcome'), 'after welcome: welcome is completed', $failures);
assertTrue(!$afterWelcome->isCompleted('store'), 'after welcome: store not completed yet', $failures);

// 3. Completing store advances to contacts (not yet "ready" - contacts,
// language_currency and ready are also implemented today).
$afterStore = new WizardProgress($afterWelcome->withCompleted('store'));
assertSame('contacts', $afterStore->currentStep(), 'after store: currentStep is contacts', $failures);
assertTrue(!$afterStore->isReady(), 'after store: not ready yet - contacts still pending', $failures);

// 3b. Completing contacts advances to language_currency (not yet "ready").
$afterContacts = new WizardProgress($afterStore->withCompleted('contacts'));
assertSame('language_currency', $afterContacts->currentStep(), 'after contacts: currentStep is language_currency', $failures);
assertTrue(!$afterContacts->isReady(), 'after contacts: not ready yet - language_currency still pending', $failures);

// 3c. Completing language_currency advances to delivery (not yet "ready" -
// delivery and ready are also implemented today).
$afterLanguageCurrency = new WizardProgress($afterContacts->withCompleted('language_currency'));
assertSame('delivery', $afterLanguageCurrency->currentStep(), 'after language_currency: currentStep is delivery', $failures);
assertTrue(!$afterLanguageCurrency->isReady(), 'after language_currency: not ready yet - delivery still pending', $failures);

// 3d. Completing delivery advances to first_product (not yet "ready" -
// first_product and ready are also implemented today).
$afterDelivery = new WizardProgress($afterLanguageCurrency->withCompleted('delivery'));
assertSame('first_product', $afterDelivery->currentStep(), 'after delivery: currentStep is first_product', $failures);
assertTrue(!$afterDelivery->isReady(), 'after delivery: not ready yet - first_product still pending', $failures);

// 3e. Completing first_product advances to the ready step (the Store
// Readiness checklist) - not straight to null, since 'ready' is itself an
// implemented step now.
$afterFirstProduct = new WizardProgress($afterDelivery->withCompleted('first_product'));
assertSame('ready', $afterFirstProduct->currentStep(), 'after first_product: currentStep is ready', $failures);
assertTrue(!$afterFirstProduct->isReady(), 'after first_product: isReady is still false (ready step itself not completed yet)', $failures);

// 3f. Completing the ready step too is "ready" (nothing left to do today).
$afterReady = new WizardProgress($afterFirstProduct->withCompleted('ready'));
assertSame(null, $afterReady->currentStep(), 'after ready: currentStep is null (all implemented steps done)', $failures);
assertTrue($afterReady->isReady(), 'after ready: isReady is true', $failures);

// 4. withCompleted() is idempotent - completing an already-completed step changes nothing.
assertSame(
    $afterReady->withCompleted('ready'),
    ['welcome', 'store', 'contacts', 'language_currency', 'delivery', 'first_product', 'ready'],
    'idempotent: re-completing ready keeps the same completed list',
    $failures
);

// 5. Unknown/garbage stored values are dropped, not trusted as-is - a
// not-a-real-step entry never surfaces as completed, and doesn't stop
// currentStep() from correctly resolving among the real (implemented) steps.
$dirty = new WizardProgress(['welcome', 'not-a-real-step', 'design']);
assertSame('store', $dirty->currentStep(), 'dirty input: unknown steps ignored, currentStep is store', $failures);
assertTrue(!$dirty->isCompleted('not-a-real-step'), 'dirty input: bogus step name never reads as completed', $failures);
assertTrue($dirty->isCompleted('design'), 'dirty input: design is a real (if unimplemented) STEPS entry, so it is tracked', $failures);

// 6. Completed-step storage is reordered to canonical STEPS order regardless
// of the order it was stored in (e.g. a hand-edited setting row).
$outOfOrder = new WizardProgress(['ready', 'contacts', 'store', 'welcome', 'language_currency', 'delivery', 'first_product']);
assertTrue($outOfOrder->isReady(), 'reorder: out-of-order storage still reads as ready', $failures);
assertSame(
    ['welcome', 'store', 'contacts', 'language_currency', 'delivery', 'first_product', 'ready'],
    $outOfOrder->withCompleted('ready'),
    'reorder: completed list normalizes to canonical STEPS order',
    $failures
);

// 7. isImplemented() distinguishes real steps from roadmap-only ones.
assertTrue($progress->isImplemented('welcome'), 'isImplemented: welcome is implemented', $failures);
assertTrue($progress->isImplemented('store'), 'isImplemented: store is implemented', $failures);
assertTrue($progress->isImplemented('contacts'), 'isImplemented: contacts is implemented', $failures);
assertTrue($progress->isImplemented('language_currency'), 'isImplemented: language_currency is implemented', $failures);
assertTrue($progress->isImplemented('delivery'), 'isImplemented: delivery is implemented', $failures);
assertTrue($progress->isImplemented('first_product'), 'isImplemented: first_product is implemented', $failures);
assertTrue($progress->isImplemented('ready'), 'isImplemented: ready is implemented', $failures);
assertTrue(!$progress->isImplemented('payment'), 'isImplemented: payment is not implemented yet', $failures);

// 8. steps() exposes the full roadmap, not just the implemented subset.
assertSame(WizardProgress::STEPS, $progress->steps(), 'steps: returns the full canonical roadmap', $failures);
assertTrue(count($progress->steps()) > count(WizardProgress::IMPLEMENTED_STEPS), 'steps: roadmap is longer than the implemented subset', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: WizardProgress smoke test (' . 12 . " scenarios)\n";
exit(0);
