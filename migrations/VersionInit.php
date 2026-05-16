<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionInit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial empty migration';
    }

    public function up(Schema $schema): void
    {
        // empty
    }

    public function down(Schema $schema): void
    {
        // empty
    }
}
