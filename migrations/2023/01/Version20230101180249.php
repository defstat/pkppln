<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230101180249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Upgrade from Symfony 2.7 (PHP 5) to Symfony 5.4 (PHP 8.1)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on MySQL.');

        $hasActiveColumn = $this->connection->executeQuery("SHOW COLUMNS FROM appuser WHERE field = 'active'")->fetch();
        $this->skipIf((bool) $hasActiveColumn, 'Not needed to apply the migration');

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_EE8A7C7492FC23A8 ON appuser');
        $this->addSql('DROP INDEX UNIQ_EE8A7C74A0D96FBF ON appuser');
        $this->addSql(
            'ALTER TABLE appuser
            RENAME COLUMN enabled TO active,
            CHANGE institution affiliation VARCHAR(64) NOT NULL,
            CHANGE confirmation_token reset_token VARCHAR(255) DEFAULT NULL,
            CHANGE password_requested_at reset_expiry DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE last_login login DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            DROP username,
            DROP username_canonical,
            DROP email_canonical,
            DROP salt,
            DROP locked,
            DROP expired,
            DROP expires_at,
            DROP credentials_expired,
            DROP credentials_expire_at,
            DROP notify,
            CHANGE email email VARCHAR(180) NOT NULL,
            CHANGE fullname fullname VARCHAR(64) NOT NULL'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EE8A7C74E7927C74 ON appuser (email)');
        $this->addSql(
            'ALTER TABLE au_container ADD created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
        $this->addSql(
            'ALTER TABLE blacklist ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE created created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
        $this->addSql('CREATE FULLTEXT INDEX IDX_3B175385D17F50A6 ON blacklist (uuid)');
        $this->addSql(
            'ALTER TABLE deposit CHANGE received created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            RENAME COLUMN pln_state TO lockss_state,
            ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            DROP package_path,
            DROP error_count,
            CHANGE file_type file_type VARCHAR(255) DEFAULT NULL,
            CHANGE deposit_receipt deposit_receipt VARCHAR(2048) DEFAULT NULL,
            CHANGE COLUMN `journal_version` `version` VARCHAR(15) NOT NULL DEFAULT \'2.4.8\''
        );
        $this->addSql('CREATE FULLTEXT INDEX IDX_95DB9D39E2AB67BDF47645AE ON deposit (deposit_uuid, url)');
        $this->addSql(
            'ALTER TABLE document ADD created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE updated updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
        $this->addSql(
            'ALTER TABLE journal ADD created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE title title VARCHAR(255) DEFAULT NULL,
            CHANGE issn issn VARCHAR(9) DEFAULT NULL,
            CHANGE email email VARCHAR(255) DEFAULT NULL,
            CHANGE publisher_name publisher_name VARCHAR(255) DEFAULT NULL,
            CHANGE COLUMN ojs_version version VARCHAR(12) NULL DEFAULT NULL'
        );
        $this->addSql('CREATE FULLTEXT INDEX IDX_C1A7E74DD17F50A62B36786B9FC5D7F6F47645AEE7927C74BF3AAE51E33 ON journal (uuid, title, issn, url, email, publisher_name, publisher_url)');
        $this->addSql(
            'ALTER TABLE term_of_use DROP lang_code,
            CHANGE created created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE updated updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
        $this->addSql(
            'ALTER TABLE term_of_use_history ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE created created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
        $this->addSql(
            'ALTER TABLE whitelist ADD updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            CHANGE created created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\''
        );
        $this->addSql('CREATE FULLTEXT INDEX IDX_CB069864D17F50A6 ON whitelist (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
