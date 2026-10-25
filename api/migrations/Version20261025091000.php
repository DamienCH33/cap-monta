<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Comment avez-vous connu Cap Monta ? » : the owner's optional answer at registration
 * (App\Enum\HeardFrom). Existing accounts have none.
 */
final class Version20261025091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Canal par lequel le propriétaire a connu le site (user.heard_from)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD heard_from VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP heard_from');
    }
}
