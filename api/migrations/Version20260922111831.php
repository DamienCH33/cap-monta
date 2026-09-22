<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922111831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accommodation: pets policy (allowed, on request, not allowed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ADD pets_policy VARCHAR(255) DEFAULT \'on_request\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation DROP pets_policy');
    }
}
