<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Prévenez-moi » : the alerts left by guests who found nothing free (App\Entity\StayAlert).
 * The checks repeat in the database what the API validates: a stay, a few guests, a resort.
 */
final class Version20261025090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alertes « Prévenez-moi » (stay_alert)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE stay_alert (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                locale VARCHAR(2) NOT NULL,
                arrival DATE NOT NULL,
                departure DATE NOT NULL,
                guests SMALLINT NOT NULL,
                resort VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                notified_slugs JSON DEFAULT '[]' NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT stay_alert_dates_check CHECK (departure > arrival),
                CONSTRAINT stay_alert_guests_check CHECK (guests BETWEEN 1 AND 50),
                CONSTRAINT stay_alert_resort_check CHECK (resort IS NULL OR resort IN ('chm', 'euronat'))
            )
            SQL);
        $this->addSql('CREATE INDEX stay_alert_arrival_idx ON stay_alert (arrival)');
        $this->addSql('CREATE INDEX stay_alert_email_idx ON stay_alert (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stay_alert');
    }
}
