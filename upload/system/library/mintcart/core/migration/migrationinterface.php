<?php
namespace MintCart\Core\Migration;

/**
 * Contract for a single MintCart schema migration.
 *
 * A migration file (see MigrationManager for the naming/loading convention)
 * must `return` an object implementing this interface.
 */
interface MigrationInterface
{
    /**
     * Apply the migration. Throw to abort — MigrationManager rolls back
     * and stops the run, it does not continue to later migrations.
     *
     * @param object $db OpenCart \DB instance (query()/escape()).
     */
    public function up(object $db): void;
}
