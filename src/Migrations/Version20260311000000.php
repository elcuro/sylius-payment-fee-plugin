<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment fee fields (calculator, calculator_configuration, tax_category_id) to sylius_payment_method';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_payment_method ADD calculator LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_payment_method ADD calculator_configuration JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_payment_method ADD tax_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_payment_method ADD CONSTRAINT FK_A75B0B0D9DF894ED FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id)');
        $this->addSql('CREATE INDEX IDX_A75B0B0D9DF894ED ON sylius_payment_method (tax_category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_payment_method DROP FOREIGN KEY FK_A75B0B0D9DF894ED');
        $this->addSql('DROP INDEX IDX_A75B0B0D9DF894ED ON sylius_payment_method');
        $this->addSql('ALTER TABLE sylius_payment_method DROP calculator');
        $this->addSql('ALTER TABLE sylius_payment_method DROP calculator_configuration');
        $this->addSql('ALTER TABLE sylius_payment_method DROP tax_category_id');
    }
}
