<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106152345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema - creates all tables for the Kochdienst application';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE availabilities (id INT AUTO_INCREMENT NOT NULL, party_id INT NOT NULL, kita_year_id INT NOT NULL, available_dates JSON NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D7FC41EF213C1059 (party_id), INDEX IDX_D7FC41EFEC9DD662 (kita_year_id), UNIQUE INDEX party_year_unique (party_id, kita_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cooking_assignments (id INT AUTO_INCREMENT NOT NULL, party_id INT NOT NULL, kita_year_id INT NOT NULL, assigned_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', is_manually_assigned TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C0F30B4C213C1059 (party_id), INDEX IDX_C0F30B4CEC9DD662 (kita_year_id), INDEX assigned_date_idx (assigned_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE holidays (id INT AUTO_INCREMENT NOT NULL, kita_year_id INT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', name VARCHAR(255) NOT NULL, INDEX IDX_3A66A10CEC9DD662 (kita_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kita_years (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE last_year_cookings (id INT AUTO_INCREMENT NOT NULL, party_id INT NOT NULL, kita_year_id INT NOT NULL, last_cooking_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', cooking_count INT NOT NULL, INDEX IDX_F3EB3959213C1059 (party_id), INDEX IDX_F3EB3959EC9DD662 (kita_year_id), UNIQUE INDEX party_year_unique (party_id, kita_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parties (id INT AUTO_INCREMENT NOT NULL, children JSON NOT NULL, email VARCHAR(255) DEFAULT NULL, parent_names JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `users` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vacations (id INT AUTO_INCREMENT NOT NULL, kita_year_id INT NOT NULL, start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', name VARCHAR(255) NOT NULL, INDEX IDX_3B829067EC9DD662 (kita_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EF213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EFEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cooking_assignments ADD CONSTRAINT FK_C0F30B4C213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cooking_assignments ADD CONSTRAINT FK_C0F30B4CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE holidays ADD CONSTRAINT FK_3A66A10CEC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959213C1059 FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE last_year_cookings ADD CONSTRAINT FK_F3EB3959EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vacations ADD CONSTRAINT FK_3B829067EC9DD662 FOREIGN KEY (kita_year_id) REFERENCES kita_years (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availabilities DROP FOREIGN KEY FK_D7FC41EF213C1059');
        $this->addSql('ALTER TABLE availabilities DROP FOREIGN KEY FK_D7FC41EFEC9DD662');
        $this->addSql('ALTER TABLE cooking_assignments DROP FOREIGN KEY FK_C0F30B4C213C1059');
        $this->addSql('ALTER TABLE cooking_assignments DROP FOREIGN KEY FK_C0F30B4CEC9DD662');
        $this->addSql('ALTER TABLE holidays DROP FOREIGN KEY FK_3A66A10CEC9DD662');
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959213C1059');
        $this->addSql('ALTER TABLE last_year_cookings DROP FOREIGN KEY FK_F3EB3959EC9DD662');
        $this->addSql('ALTER TABLE vacations DROP FOREIGN KEY FK_3B829067EC9DD662');
        $this->addSql('DROP TABLE availabilities');
        $this->addSql('DROP TABLE cooking_assignments');
        $this->addSql('DROP TABLE holidays');
        $this->addSql('DROP TABLE kita_years');
        $this->addSql('DROP TABLE last_year_cookings');
        $this->addSql('DROP TABLE parties');
        $this->addSql('DROP TABLE `users`');
        $this->addSql('DROP TABLE vacations');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
