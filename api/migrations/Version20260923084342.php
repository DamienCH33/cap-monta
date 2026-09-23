<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lectures d'annonce par l'assistant (ADR 027). Les DROP INDEX …_no_overlap proposés par
 * Doctrine ont été retirés (ADR 010).
 */
final class Version20260923084342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table listing_import : textes collés par les propriétaires et lectures par l\'assistant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE listing_import (id UUID NOT NULL, source_text TEXT NOT NULL, text_hash VARCHAR(64) NOT NULL, status VARCHAR(255) NOT NULL, failure VARCHAR(255) DEFAULT NULL, attempts SMALLINT NOT NULL, model VARCHAR(80) DEFAULT NULL, result JSON DEFAULT NULL, last_error TEXT DEFAULT NULL, input_tokens INT DEFAULT NULL, output_tokens INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX listing_import_owner_hash ON listing_import (owner_id, text_hash)');
        $this->addSql('CREATE INDEX IDX_79B6EFCE7E3C61F9 ON listing_import (owner_id)');
        $this->addSql('ALTER TABLE listing_import ADD CONSTRAINT FK_79B6EFCE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listing_import DROP CONSTRAINT FK_79B6EFCE7E3C61F9');
        $this->addSql('DROP TABLE listing_import');
    }
}
