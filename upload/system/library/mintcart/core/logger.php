<?php
namespace MintCart\Core;

/**
 * Basic MintCart logging, kept separate from OpenCart's own error.log so
 * Mint-layer events (migrations, adapters, wizard steps, ...) are easy to
 * find without wrapping/replacing OpenCart's Log class.
 *
 * Requires DIR_LOGS to be defined (i.e. OpenCart's config.php already
 * loaded), same precondition as OpenCart's own \Log usage.
 */
final class Logger
{
    private \Log $log;

    public function __construct(string $filename = 'mintcart.log')
    {
        $this->log = new \Log($filename);
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->write('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $this->log->write('[' . $level . '] ' . $message);
    }
}
