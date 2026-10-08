<?php
/**
 * Standalone smoke test for MintCart\Design\SvgValidator.
 *
 * Run: php tests/mintcart/design/svg-validator-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/svgvalidator.php';

use MintCart\Design\SvgValidator;

$failures = [];

function check(bool $expected, string $svg, string $message, array &$failures): void
{
    if (SvgValidator::isSafe($svg) !== $expected) {
        $failures[] = $message . ' (expected ' . ($expected ? 'safe' : 'refused') . ')';
    }
}

$ns = 'xmlns="http://www.w3.org/2000/svg"';

// Accepted: ordinary exported logos.
check(true, "<svg $ns viewBox=\"0 0 10 10\"><path d=\"M0 0h10v10z\" fill=\"#2a9\"/></svg>", 'plain path', $failures);
check(true, "<?xml version=\"1.0\"?><svg $ns xmlns:xlink=\"http://www.w3.org/1999/xlink\"><defs><linearGradient id=\"g\"/></defs><rect fill=\"url(#g)\" width=\"5\" height=\"5\"/><use xlink:href=\"#g\"/></svg>", 'gradient + internal xlink:href', $failures);
check(true, "<svg $ns><style>.a{fill:#000}</style><text class=\"a\">Mint</text></svg>", 'style block + text', $failures);
check(true, "<svg $ns><image href=\"data:image/png;base64,iVBORw0KGgo=\"/></svg>", 'embedded PNG data URI', $failures);

// Refused.
check(false, "<svg $ns><script>alert(1)</script></svg>", 'script element', $failures);
check(false, "<svg $ns onload=\"alert(1)\"></svg>", 'onload attribute', $failures);
check(false, "<svg $ns><rect onclick=\"alert(1)\"/></svg>", 'onclick on child', $failures);
check(false, "<svg $ns><a href=\"javascript:alert(1)\"><text>x</text></a></svg>", 'javascript: href', $failures);
check(false, "<svg $ns xmlns:xlink=\"http://www.w3.org/1999/xlink\"><a xlink:href=\" JavaScript:alert(1)\"/></svg>", 'javascript: xlink:href with space/case', $failures);
check(false, "<svg $ns><image href=\"https://evil.example/x.png\"/></svg>", 'external image', $failures);
check(false, "<svg $ns><image href=\"data:image/svg+xml;base64,PHN2Zz4=\"/></svg>", 'nested svg data URI', $failures);
check(false, "<svg $ns><foreignObject><div xmlns=\"http://www.w3.org/1999/xhtml\">x</div></foreignObject></svg>", 'foreignObject', $failures);
check(false, "<!DOCTYPE svg [<!ENTITY x SYSTEM \"file:///etc/passwd\">]><svg $ns>&x;</svg>", 'XXE doctype', $failures);
check(false, "<svg $ns><style>@import url(https://evil.example/a.css);</style></svg>", 'style @import', $failures);
check(false, "<svg $ns><rect style=\"fill:url(javascript:alert(1))\"/></svg>", 'javascript in inline style', $failures);
check(false, "<html><body>not svg</body></html>", 'not an svg root', $failures);
check(false, "<svg $ns><rect></svg>", 'malformed XML', $failures);
check(false, '', 'empty', $failures);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: $failure\n");
    }
    exit(1);
}

echo "SvgValidator: all tests passed\n";
