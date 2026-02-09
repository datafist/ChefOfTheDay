<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notfall-Zuweisungen markierbar machen.
 * Fügt is_emergency_assignment Spalte zur cooking_assignments Tabelle hinzu.
 */
final class Version20260209100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CookingAssignment: is_emergency_assignment Feld hinzufügen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cooking_assignments ADD is_emergency_assignment TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cooking_assignments DROP COLUMN is_emergency_assignment');
    }
}
