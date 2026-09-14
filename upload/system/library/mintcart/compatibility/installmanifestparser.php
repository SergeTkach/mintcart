<?php

declare(strict_types=1);

namespace MintCart\Compatibility;

/**
 * Parses an OCMOD `install.xml` document into a normalized, plain-array
 * shape the rest of `MintCart\Compatibility` (and, later, an admin screen)
 * can work with, without ever touching `\SimpleXMLElement`/libxml directly
 * again (`MINTCART_TECHNICAL_PLAN.md` §39/§70,
 * `MINTCART_FUNCTIONAL_SCOPE.md` §29 "Compatibility Checker").
 *
 * Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`, no
 * filesystem access) — same "resolved values in, normalized contract out"
 * shape as `ProductViewProvider`/`CartService`, so it is unit-testable
 * against plain XML strings.
 *
 * This class only *reads* an install.xml; it never applies, validates
 * against a real target file, or blocks anything (Functional Scope §29:
 * "Не блокувати install лише за heuristic warning" — the *checker*, not
 * this parser, decides what's heuristically worth flagging).
 */
final class InstallManifestParser
{
    /**
     * @param string $xml Raw `install.xml` contents (e.g. pasted or
     *        uploaded by an admin), not a file path.
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     version: string,
     *     author: string,
     *     link: string,
     *     files: list<array{path: string, operation_count: int, content: string}>
     * }
     *
     * @throws \InvalidArgumentException When `$xml` is not well-formed XML,
     *         or its root element is not `<modification>`.
     */
    public function parse(string $xml): array
    {
        $previousSetting = libxml_use_internal_errors(true);
        $simplexml = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if ($simplexml === false) {
            throw new \InvalidArgumentException('Malformed install.xml: not well-formed XML.');
        }

        $rootName = $simplexml->getName();
        if ($rootName !== 'modification') {
            throw new \InvalidArgumentException(
                "Malformed install.xml: expected root element <modification>, got <{$rootName}>."
            );
        }

        $files = [];
        foreach ($simplexml->file as $fileNode) {
            $path = ltrim((string) $fileNode['path'], '/');
            if ($path === '') {
                // A <file> without a usable path attribute can't be
                // classified or checked for conflicts — skip it rather
                // than fatal the whole manifest over one malformed entry.
                continue;
            }

            $operationCount = 0;
            $contentParts = [];
            foreach ($fileNode->operation as $operation) {
                $operationCount++;
                foreach (['search', 'add'] as $tag) {
                    if (isset($operation->{$tag})) {
                        $contentParts[] = (string) $operation->{$tag};
                    }
                }
            }

            $files[] = [
                'path' => $path,
                'operation_count' => $operationCount,
                'content' => implode("\n", $contentParts),
            ];
        }

        return [
            'name' => trim((string) ($simplexml->name ?? '')),
            'code' => trim((string) ($simplexml->code ?? '')),
            'version' => trim((string) ($simplexml->version ?? '')),
            'author' => trim((string) ($simplexml->author ?? '')),
            'link' => trim((string) ($simplexml->link ?? '')),
            'files' => $files,
        ];
    }
}
