<?php
namespace MintCart\Onboarding;

/**
 * Pure step-sequencing logic for the Setup Wizard (`MINTCART_FUNCTIONAL_SCOPE.md`
 * §2, `MINTCART_IMPLEMENTATION_GUIDE.md` §54).
 *
 * No OpenCart dependency, same shape as ProductViewProvider/FooterViewProvider -
 * this class only knows the canonical step order, which steps have a real,
 * working implementation today, and which of those are already completed. It
 * does not read or write settings itself; the caller (ControllerMintcartWizard)
 * loads the completed-steps list from storage and passes it in, then persists
 * whatever withCompleted() returns.
 *
 * STEPS lists the full onboarding flow from the Implementation Guide's phase
 * list so the wizard's step nav can show the whole roadmap, but only
 * IMPLEMENTED_STEPS have real content today - visiting/submitting any other
 * step falls back to the current resume point (CLAUDE.md §85: no API/UI
 * ahead of a real, working consumer).
 */
final class WizardProgress
{
    public const STEPS = [
        'welcome',
        'store',
        'contacts',
        'language_currency',
        'delivery',
        'payment',
        'design',
        'first_product',
        'ready',
    ];

    public const IMPLEMENTED_STEPS = ['welcome', 'store'];

    /** @var string[] */
    private array $completed;

    /**
     * @param string[] $completedSteps Raw completed-step list from storage;
     *                                 unknown values are dropped and the
     *                                 result is reordered to match STEPS, so
     *                                 storage never has to be trusted as-is.
     */
    public function __construct(array $completedSteps = [])
    {
        $this->completed = array_values(array_intersect(self::STEPS, $completedSteps));
    }

    /**
     * @return string[]
     */
    public function steps(): array
    {
        return self::STEPS;
    }

    public function isImplemented(string $step): bool
    {
        return in_array($step, self::IMPLEMENTED_STEPS, true);
    }

    public function isCompleted(string $step): bool
    {
        return in_array($step, $this->completed, true);
    }

    /**
     * The first implemented step not yet completed, or null once every
     * implemented step is done (the caller renders a "more steps coming
     * soon" state in that case rather than a step that doesn't exist yet).
     */
    public function currentStep(): ?string
    {
        foreach (self::IMPLEMENTED_STEPS as $step) {
            if (!$this->isCompleted($step)) {
                return $step;
            }
        }

        return null;
    }

    public function isReady(): bool
    {
        return $this->currentStep() === null;
    }

    /**
     * Returns the completed-steps list the caller should persist after
     * marking $step done. Does not mutate $this - WizardProgress represents
     * one immutable read of progress, the same way ProductViewProvider
     * never mutates its input.
     *
     * @return string[]
     */
    public function withCompleted(string $step): array
    {
        if (!in_array($step, self::STEPS, true) || $this->isCompleted($step)) {
            return $this->completed;
        }

        $completed = $this->completed;
        $completed[] = $step;

        return array_values(array_intersect(self::STEPS, $completed));
    }
}
