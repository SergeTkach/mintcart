<?php
namespace MintCart\Catalog;

/**
 * Builds the nested category tree the storefront menu and the category
 * module render (up to three levels).
 *
 * OpenCart 3's `common/menu` and `extension/module/category` controllers each
 * hand-roll exactly two levels. This is the one place that walks the tree, so
 * both controllers (and any future consumer) get the same shape and a third
 * level without a second copy of the loop.
 *
 * No OpenCart dependency: the caller passes how to read the children of a
 * category, how to build a link from a `path`, and (optionally) how to count
 * products - plain callables - so it is unit-testable without bootstrapping
 * OpenCart. Node shape (every level):
 *
 *   category_id, name (with " (n)" appended below level 1 when a counter is
 *   given), href, column, children[]
 */
final class CategoryTree
{
    /** @var callable(int): array<int, array<string, mixed>> */
    private $childrenOf;

    /** @var callable(string): string */
    private $link;

    /** @var (callable(int): int)|null */
    private $count;

    private int $maxDepth;

    /**
     * @param callable(int): array<int, array<string, mixed>> $childrenOf rows with
     *        `category_id`, `name`, `top`, `column` for a parent id (0 = root)
     * @param callable(string): string $link builds the URL for `path=1_2_3`
     * @param (callable(int): int)|null $count products in a category incl. its
     *        subcategories; null = no counts in the names
     */
    public function __construct(callable $childrenOf, callable $link, ?callable $count = null, int $maxDepth = 3)
    {
        $this->childrenOf = $childrenOf;
        $this->link = $link;
        $this->count = $count;
        $this->maxDepth = max(1, $maxDepth);
    }

    /**
     * @param bool $topOnly keep only root categories flagged `top` (the menu
     *        does; the category module lists every root category)
     * @param bool $countRoots also append the product count to root names
     *        (the category module does; the menu does not)
     * @param int|null $expandRoot when set, only that root gets children (the
     *        category module expands just the active branch)
     * @return array<int, array<string, mixed>>
     */
    public function build(bool $topOnly = false, bool $countRoots = false, ?int $expandRoot = null): array
    {
        return $this->level(0, [], 1, $topOnly, $countRoots, $expandRoot);
    }

    /**
     * @param array<int, int> $path ids from the root down to (not including) $parentId's children
     * @return array<int, array<string, mixed>>
     */
    private function level(int $parentId, array $path, int $depth, bool $topOnly, bool $countRoots, ?int $expandRoot): array
    {
        $nodes = [];

        foreach (($this->childrenOf)($parentId) as $row) {
            if ($depth === 1 && $topOnly && empty($row['top'])) {
                continue;
            }

            $id = (int) $row['category_id'];
            $nodePath = array_merge($path, [$id]);
            $name = (string) $row['name'];

            if (($depth > 1 || $countRoots) && $this->count !== null) {
                $name .= ' (' . ($this->count)($id) . ')';
            }

            $expand = $depth < $this->maxDepth && !($depth === 1 && $expandRoot !== null && $expandRoot !== $id);

            $nodes[] = [
                'category_id' => $id,
                'name' => $name,
                'href' => ($this->link)(implode('_', $nodePath)),
                'column' => !empty($row['column']) ? (int) $row['column'] : 1,
                'children' => $expand ? $this->level($id, $nodePath, $depth + 1, false, $countRoots, $expandRoot) : [],
            ];
        }

        return $nodes;
    }
}
