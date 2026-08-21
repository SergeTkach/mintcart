<?php
namespace MintCart\Core;

/**
 * Thin bridge into OpenCart's existing service registry.
 *
 * MintCart does not introduce a parallel dependency injection container.
 * Mint services accept the OpenCart \Registry (or this wrapper) and pull
 * what they need from it, the same way OpenCart controllers/models already
 * do via $this->registry->get('db'), ->get('config'), etc.
 *
 * A single boot() call is expected once per request, from whichever
 * MintCart entry point first needs the registry (an admin controller
 * extension, the Setup Wizard, the update runner, ...). Foundation phase
 * does not wire an automatic call site — that lands with the first real
 * caller, per the "no premature abstraction" rule in CLAUDE.md.
 */
final class Bootstrap
{
    private static ?self $instance = null;

    private \Registry $registry;

    public function __construct(\Registry $registry)
    {
        $this->registry = $registry;
    }

    public static function boot(\Registry $registry): self
    {
        if (self::$instance === null) {
            self::$instance = new self($registry);
        }

        return self::$instance;
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    public function registry(): \Registry
    {
        return $this->registry;
    }

    public function get(string $key)
    {
        return $this->registry->get($key);
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }
}
