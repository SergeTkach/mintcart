<?php
namespace MintCart\Mail;

/**
 * Resolves the hostname `Mail\Smtp::send()` (`upload/system/library/mail/smtp.php`,
 * stock OpenCart, unmodified besides the three call sites that now use this
 * class) sends in its `EHLO`/`HELO` commands.
 *
 * Fix MC-0013 (`docs/fixes/registry.md`): the stock code used
 * `getenv('SERVER_NAME')` directly, which is only populated inside a real
 * web request under most SAPI/webserver configurations. Any CLI/cron
 * context (Functional Scope §7's batch jobs, Technical Plan §33's Update
 * Manager, or simply a queued/deferred order-confirmation send) gets `false`
 * there, producing a bare `EHLO \r\n`/`HELO \r\n` with no hostname argument
 * - malformed per RFC 5321 §4.1.1.1, and several real-world SMTP servers
 * (Postfix with `smtpd_helo_restrictions = reject_invalid_helo_hostname`,
 * some greylisting/anti-spam appliances) reject it outright, silently
 * breaking outgoing mail.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \Config) so it
 * is unit-testable in isolation, same reasoning as
 * `MintCart\Catalog\CategoryMoveValidator` (Phase 17).
 */
final class HeloHostname
{
    /**
     * @return string Never empty - always a usable EHLO/HELO argument.
     */
    public static function resolve(): string
    {
        $serverName = getenv('SERVER_NAME');

        if (is_string($serverName) && $serverName !== '') {
            return $serverName;
        }

        $unameHostname = php_uname('n');

        if (is_string($unameHostname) && $unameHostname !== '') {
            return $unameHostname;
        }

        return 'localhost';
    }
}
