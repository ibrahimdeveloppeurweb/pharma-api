<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531211501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation ADD create_by_id INT DEFAULT NULL, ADD update_by_id INT DEFAULT NULL, ADD remove_by_id INT DEFAULT NULL, ADD uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559E085865 FOREIGN KEY (create_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CA83C286 FOREIGN KEY (update_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849551748B4A5 FOREIGN KEY (remove_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C84955D17F50A6 ON reservation (uuid)');
        $this->addSql('CREATE INDEX IDX_42C849559E085865 ON reservation (create_by_id)');
        $this->addSql('CREATE INDEX IDX_42C84955CA83C286 ON reservation (update_by_id)');
        $this->addSql('CREATE INDEX IDX_42C849551748B4A5 ON reservation (remove_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559E085865');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CA83C286');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849551748B4A5');
        $this->addSql('DROP INDEX UNIQ_42C84955D17F50A6 ON reservation');
        $this->addSql('DROP INDEX IDX_42C849559E085865 ON reservation');
        $this->addSql('DROP INDEX IDX_42C84955CA83C286 ON reservation');
        $this->addSql('DROP INDEX IDX_42C849551748B4A5 ON reservation');
        $this->addSql('ALTER TABLE reservation DROP create_by_id, DROP update_by_id, DROP remove_by_id, DROP uuid, DROP updated_at, DROP deleted_at, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
