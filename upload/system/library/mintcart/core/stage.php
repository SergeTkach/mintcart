<?php
namespace MintCart\Core;

/**
 * Fires a documented `{prefix}.{name}.before` / `{prefix}.{name}.after`
 * pair of OpenCart events around a meaningful controller stage, so a
 * controller action gets fine-grained extension points without hand-rolling
 * `$this->event->trigger()` twice per stage (`MINTCART_TECHNICAL_PLAN.md`
 * §15B, `MINTCART_IMPLEMENTATION_GUIDE.md` §38B: "можна створити невеликий
 * reusable stage/pipeline helper, якщо він не ускладнює читання коду").
 *
 * Naming is deliberately dot-separated (`catalog.manufacturer.listing.before`)
 * rather than OpenCart's own slash-separated route-level convention
 * (`controller/product/category/before`, see
 * `upload/system/engine/loader.php`) - these are MintCart-defined sub-method
 * stage boundaries, not OpenCart's route/model/view lifecycle events, and
 * the different separator keeps the two namespaces from colliding or being
 * mistaken for one another in the admin Events screen.
 *
 * Deliberately untyped against OpenCart's `\Event` class: `run()` only
 * needs an object with a `trigger(string $event, array $args = [])` method
 * (duck-typed), so this stays unit-testable without bootstrapping OpenCart's
 * registry/engine classes, the same "no OpenCart dependency" shape as
 * `Diagnostics`/`StoreReadiness`. In production the caller passes
 * `$this->event` (`Controller::__get('event')` resolves to OpenCart's real
 * `\Event` instance via the registry).
 *
 * `$data` is passed to both the before and after event by reference -
 * OpenCart's native `Event::trigger()` -> `Action::execute()` ->
 * `call_user_func_array()` chain already supports a listener mutating a
 * by-reference argument (exactly how `Loader::controller()`'s own
 * `controller/*\/before` event lets a registered extension rewrite
 * `$route`/`$data` before the real controller runs) - a listener registered
 * via Admin > Extensions > Events can read or add to `$data` the same way,
 * without MintCart inventing a second event mechanism.
 *
 * If `$work()` throws, the exception propagates and the `.after` event does
 * not fire - firing it after a failed stage would misrepresent what
 * happened, and existing MintCart call sites already own their own
 * try/catch around risky work (see `category.php`'s `CanonicalUrlBuilder`
 * wiring) rather than a shared primitive swallowing stage failures.
 */
final class Stage
{
    /** @var object Duck-typed: must expose trigger(string $event, array $args = []) */
    private $event;

    private string $prefix;

    /**
     * @param object $event Must expose trigger(string $event, array $args = [])
     * @param string $prefix e.g. "catalog.manufacturer"
     */
    public function __construct($event, string $prefix)
    {
        $this->event = $event;
        $this->prefix = $prefix;
    }

    /**
     * @param string $name Stage name, e.g. "breadcrumbs" -> fires
     *                      "{prefix}.breadcrumbs.before" / ".after"
     * @param array $data Passed by reference to both events
     * @param callable $work
     * @return mixed $work()'s return value, unchanged
     */
    public function run(string $name, array &$data, callable $work)
    {
        $this->event->trigger($this->prefix . '.' . $name . '.before', [&$data]);

        $result = $work();

        $this->event->trigger($this->prefix . '.' . $name . '.after', [&$data]);

        return $result;
    }
}
