<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2: LastYearCooking von KitaYear entkoppeln.
 * - kita_year_id wird nullable (war NOT NULL)
 * - ON DELETE CASCADE wird zu ON DELETE SET NULL
 * → LastYearCooking-Einträge überleben das Löschen eines KitaYear
 */
final class Version20260120100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LastYearCooking: kita_year_id nullable machen + ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        // 1. Bestehende FK entfernen
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959EC9DD662');

        // 2. Spalte nullable machen
        $this->addSql('ALTER TABLE last_year_cookings MODIFY kita_year_id INT DEFAULT NULL');

        // 3. Neue FK mit SET NULL anlegen
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // 1. FK entfernen
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959EC9DD662');

        // 2. NULL-Werte entfernen (können nicht nach NOT NULL konvertiert werden wenn NULLs existieren)
        $this->addSql('DELETE FROM last_year_cookings WHERE kita_year_id IS NULL');

        // 3. Spalte wieder NOT NULL machen
        $this->addSql('ALTER TABLE last_year_cookings MODIFY kita_year_id INT NOT NULL');

        // 4. Alte FK mit CASCADE wiederherstellen
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
    }
}
