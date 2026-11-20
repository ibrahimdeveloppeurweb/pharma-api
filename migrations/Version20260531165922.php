<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531165922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, pharmacie_id INT NOT NULL, recherche_publique_id INT DEFAULT NULL, patient_nom VARCHAR(255) NOT NULL, patient_telephone VARCHAR(255) NOT NULL, medicaments JSON NOT NULL COMMENT \'(DC2Type:json)\', status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_42C84955BC6D351B (pharmacie_id), INDEX IDX_42C84955BEB378B2 (recherche_publique_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955BC6D351B FOREIGN KEY (pharmacie_id) REFERENCES pharmacie (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955BEB378B2 FOREIGN KEY (recherche_publique_id) REFERENCES recherche_publique (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955BC6D351B');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955BEB378B2');
        $this->addSql('DROP TABLE reservation');
    }
}
