<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260917141508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE district (id UUID NOT NULL, slug VARCHAR(64) NOT NULL, name VARCHAR(100) NOT NULL, resort VARCHAR(255) NOT NULL, area VARCHAR(255) DEFAULT NULL, intro TEXT DEFAULT NULL, highlights JSON NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_district_slug ON district (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_district_resort_name ON district (resort, name)');
        $this->addSql('ALTER TABLE accommodation ADD district_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation DROP district');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D385412B08FA272 FOREIGN KEY (district_id) REFERENCES district (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2D385412B08FA272 ON accommodation (district_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE district');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D385412B08FA272');
        $this->addSql('DROP INDEX IDX_2D385412B08FA272');
        $this->addSql('ALTER TABLE accommodation ADD district VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation DROP district_id');
    }
}
