<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006093249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds CASCADE DELETE constraints to all foreign keys for GDPR compliance and hard deletion';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availabilities DROP FOREIGN KEY FK_D7FC41EF213C1059');
        $this->addSql('ALTER TABLE availabilities DROP FOREIGN KEY FK_D7FC41EFEC9DD662');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EF213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EFEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cooking_assignments DROP FOREIGN KEY FK_C0F30B4C213C1059');
        $this->addSql('ALTER TABLE cooking_assignments DROP FOREIGN KEY FK_C0F30B4CEC9DD662');
        $this->addSql('ALTER TABLE cooking_assignments ADD CONSTRAINT FK_C0F30B4C213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cooking_assignments ADD CONSTRAINT FK_C0F30B4CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE holidays DROP FOREIGN KEY FK_3A66A10CEC9DD662');
        $this->addSql('ALTER TABLE holidays ADD CONSTRAINT FK_3A66A10CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959213C1059');
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959EC9DD662');
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vacations DROP FOREIGN KEY FK_3B829067EC9DD662');
        $this->addSql('ALTER TABLE vacations ADD CONSTRAINT FK_3B829067EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availabilities DROP FOREIGN KEY FK_D7FC41EF213C1059');
        $this->addSql('ALTER TABLE availabilities DROP FOREIGN KEY FK_D7FC41EFEC9DD662');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EF213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EFEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959213C1059');
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959EC9DD662');
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE vacations DROP FOREIGN KEY FK_3B829067EC9DD662');
        $this->addSql('ALTER TABLE vacations ADD CONSTRAINT FK_3B829067EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE cooking_assignments DROP FOREIGN KEY FK_C0F30B4C213C1059');
        $this->addSql('ALTER TABLE cooking_assignments DROP FOREIGN KEY FK_C0F30B4CEC9DD662');
        $this->addSql('ALTER TABLE cooking_assignments ADD CONSTRAINT FK_C0F30B4C213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE cooking_assignments ADD CONSTRAINT FK_C0F30B4CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE holidays DROP FOREIGN KEY FK_3A66A10CEC9DD662');
        $this->addSql('ALTER TABLE holidays ADD CONSTRAINT FK_3A66A10CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
