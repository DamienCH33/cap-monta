<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921133825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photos des logements';
    }

    public function up(Schema $schema): void
    {
        // Doctrine proposes to drop the *_no_overlap exclusion constraints: removed (ADR 010).
        $this->addSql('CREATE TABLE photo (id UUID NOT NULL, position SMALLINT NOT NULL, width SMALLINT NOT NULL, height SMALLINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accommodation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX photo_accommodation_position_idx ON photo (accommodation_id, position)');
        $this->addSql('CREATE INDEX IDX_14B784188F3692CD ON photo (accommodation_id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784188F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP CONSTRAINT FK_14B784188F3692CD');
        $this->addSql('DROP TABLE photo');
    }
}
