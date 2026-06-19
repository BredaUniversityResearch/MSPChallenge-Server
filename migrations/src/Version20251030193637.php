<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;
final class Version20251030193637 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Changed layer table json_document fields to type TEXT and added default value (DC2Type:json). Changed default value for layer_text_info.';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `layer`
        CHANGE `layer_type` `layer_type` text NULL COMMENT '(DC2Type:json)' AFTER `layer_kpi_category`,
        CHANGE `layer_info_properties` `layer_info_properties` text NULL COMMENT '(DC2Type:json)' AFTER `layer_depth`,
        CHANGE `layer_text_info` `layer_text_info` text NOT NULL DEFAULT '{\"#type\":\"App\\\\Entity\\\\SessionAPI\\\\LayerTextInfo\"}' COMMENT '(DC2Type:json)' AFTER `layer_information`,
        CHANGE `layer_states` `layer_states` text NULL DEFAULT '[{\"state\":\"ASSEMBLY\",\"time\":2},{\"state\":\"ACTIVE\",\"time\":10},{\"state\":\"DISMANTLE\",\"time\":2}]' COMMENT '(DC2Type:json)' AFTER `layer_text_info`,
        CHANGE `layer_raster` `layer_raster` text NULL COMMENT '(DC2Type:json)' AFTER `layer_states`,
        CHANGE `layer_tags` `layer_tags` text NULL COMMENT '(DC2Type:json)' AFTER `layer_entity_value_max`
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `layer`
        CHANGE `layer_type` `layer_type` text DEFAULT NULL AFTER `layer_kpi_category`,
        CHANGE `layer_info_properties` `layer_info_properties` text DEFAULT NULL COMMENT '(DC2Type:json)' AFTER `layer_depth`,
        CHANGE `layer_text_info` `layer_text_info` varchar(1024) NOT NULL DEFAULT '{}' COMMENT '(DC2Type:json)' AFTER `layer_information`,
        CHANGE `layer_states` `layer_states` varchar(255) DEFAULT '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]' AFTER `layer_text_info`,
        CHANGE `layer_raster` `layer_raster` varchar(512) DEFAULT NULL AFTER `layer_states`,
        CHANGE `layer_tags` `layer_tags` text DEFAULT NULL AFTER `layer_entity_value_max`
        SQL);
    }
}
