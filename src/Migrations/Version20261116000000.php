<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Doctrine DBAL 4 (Sylius 2.3) no longer uses the "(DC2Type:…)" column comments. Of the plugin's
 * columns on sylius_payment_method only the JSON column can carry one ("(DC2Type:json)", written by
 * DBAL 3 schema tools); Version20260311000000 created it without a comment, so on such databases
 * this migration changes nothing. down() puts back the comment DBAL 3 expects.
 */
final class Version20261116000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the DBAL 3 type comment from sylius_payment_method.calculator_configuration (DBAL 4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_payment_method CHANGE calculator_configuration calculator_configuration JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_payment_method CHANGE calculator_configuration calculator_configuration JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }
}
