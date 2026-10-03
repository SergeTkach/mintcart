<?php
namespace MintCart\Design;

/**
 * Lets a MintCart or third-party extension inject markup into one of the
 * documented extension points `MINTCART_TECHNICAL_PLAN.md` §15 names
 * (`product.after_actions`, `header.before`, `cart.before_totals`, ...)
 * without patching the theme that renders them - the "Extension slots"
 * requirement `MINTCART_IMPLEMENTATION_GUIDE.md` §35 lists alongside
 * Theme API Components/Blocks.
 *
 * Deliberately a plain in-request registry, not a second event/dispatch
 * mechanism: `MintCart\Core\Stage` already exists for *controller-stage*
 * before/after hooks (mutating `$data` around a method boundary,
 * Technical Plan §15B); a slot is a different concern - a *template*
 * asking "does anything want to render inside me here", answered with
 * markup, not a mutation to intercept. Reusing Stage/Event for this would
 * force every slot consumer to accept a `\Registry`/`\Event` dependency
 * just to ask a template question, the same "no OpenCart dependency"
 * rationale `ComponentRenderer`/`ProductViewProvider` already follow
 * (`CLAUDE.md` §86 - don't reach for a heavier existing mechanism when a
 * smaller, purpose-built one is clearer).
 *
 * A listener is any `callable(array $context): string` - given whatever
 * context array the slot's own caller documents (e.g. `product.after_actions`
 * passes the rendered product's `product` array), it returns the HTML
 * fragment to inject. Multiple listeners on the same slot all render, in
 * registration order, concatenated.
 *
 * No listener is registered anywhere in this codebase yet (`render()`
 * returns `''` for every slot today) - this class exists so a template can
 * have a real, stable place to ask, before any extension needs to answer
 * (`CLAUDE.md` §85: the slot *call site* is the real consumer being proven
 * here, the same way an unused `options_summary` key was reserved in
 * `ProductViewProvider`'s contract ahead of a Product Page listing
 * context).
 */
final class SlotRegistry
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /**
     * @param callable(array<string, mixed>): string $renderer
     */
    public function listen(string $slot, callable $renderer): void
    {
        $this->listeners[$slot][] = $renderer;
    }

    public function has(string $slot): bool
    {
        return !empty($this->listeners[$slot]);
    }

    /**
     * @param array<string, mixed> $context Passed to every listener on this
     *        slot - the shape is up to whoever documents that particular
     *        slot name, not this class.
     */
    public function render(string $slot, array $context = []): string
    {
        if (empty($this->listeners[$slot])) {
            return '';
        }

        $output = '';

        foreach ($this->listeners[$slot] as $listener) {
            $output .= (string) $listener($context);
        }

        return $output;
    }
}
