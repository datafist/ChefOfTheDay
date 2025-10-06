<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006101327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds username field to users table and CASCADE DELETE constraints for GDPR compliance';
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
        
        // Add username field (temporarily nullable for data migration)
        $this->addSql('ALTER TABLE users ADD username VARCHAR(180) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL');
        
        // Data migration: Set username based on email for existing users
        $this->addSql("UPDATE users SET username = SUBSTRING_INDEX(email, '@', 1) WHERE username IS NULL");
        
        // Now make username NOT NULL and add unique index
        $this->addSql('ALTER TABLE users MODIFY username VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        
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
        $this->addSql('DROP INDEX UNIQ_1483A5E9F85E0677 ON `users`');
        $this->addSql('ALTER TABLE `users` DROP username, CHANGE email email VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE holidays DROP FOREIGN KEY FK_3A66A10CEC9DD662');
        $this->addSql('ALTER TABLE holidays ADD CONSTRAINT FK_3A66A10CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
