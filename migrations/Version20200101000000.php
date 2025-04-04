<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20200101000000 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Creation of session tables';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $sql = <<< 'SQL'
CREATE TABLE IF NOT EXISTS `country` (
  `country_id` INT NOT NULL AUTO_INCREMENT,
  `country_name` VARCHAR(45) NULL,
  `country_colour` VARCHAR(45) NULL,
  `country_is_manager` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`country_id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `user`
    -- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `user_name` VARCHAR(45) NULL,
  `user_lastupdate` DOUBLE NOT NULL,
  `user_country_id` INT NOT NULL,
  `user_loggedoff` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  INDEX `fk_user_country1_idx` (`user_country_id` ASC),
  CONSTRAINT `fk_user_country1`
    FOREIGN KEY (`user_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `plan`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan` (
  `plan_id` INT NOT NULL AUTO_INCREMENT,
  `plan_country_id` INT NOT NULL,
  `plan_name` VARCHAR(75) NOT NULL,
  `plan_description` TEXT NOT NULL DEFAULT '',
  `plan_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `plan_gametime` INT(5) NOT NULL,
  `plan_state` ENUM('DESIGN', 'CONSULTATION', 'APPROVAL', 'APPROVED', 'IMPLEMENTED', 'DELETED') NOT NULL DEFAULT 'DESIGN',
  `plan_lock_user_id` INT NULL,
  `plan_lastupdate` DOUBLE NOT NULL DEFAULT 0,
  `plan_previousstate` ENUM('NONE', 'DESIGN', 'CONSULTATION', 'APPROVAL', 'APPROVED') NOT NULL DEFAULT 'NONE',
  `plan_active` TINYINT NOT NULL DEFAULT 1,
  `plan_constructionstart` INT NULL,
  `plan_type` INT NOT NULL DEFAULT 0 COMMENT 'If a plan is energy/fishing/shipping. bit flags',
  `plan_energy_error` TINYINT(1) NOT NULL DEFAULT 0,
  `plan_alters_energy_distribution` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`plan_id`),
  INDEX `fk_plan_user2_idx` (`plan_lock_user_id` ASC),
  INDEX `fk_plan_country1_idx` (`plan_country_id` ASC),
  CONSTRAINT `fk_plan_user2`
    FOREIGN KEY (`plan_lock_user_id`)
    REFERENCES `user` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_plan_country1`
    FOREIGN KEY (`plan_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `layer`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `layer` (
  `layer_id` INT NOT NULL AUTO_INCREMENT,
  `layer_original_id` INT NULL,
  `layer_active` TINYINT(1) NOT NULL DEFAULT 1,
  `layer_selectable` TINYINT(1) NOT NULL DEFAULT 1,
  `layer_active_on_start` TINYINT(1) NOT NULL DEFAULT 0,
  `layer_toggleable` TINYINT(1) NOT NULL DEFAULT 1,
  `layer_editable` TINYINT(1) NOT NULL DEFAULT 1,
  `layer_name` VARCHAR(125) NOT NULL DEFAULT '',
  `layer_geotype` VARCHAR(75) NOT NULL DEFAULT '',
  `layer_short` VARCHAR(75) NOT NULL DEFAULT '',
  `layer_group` VARCHAR(75) NOT NULL DEFAULT '',
  `layer_tooltip` VARCHAR(512) NOT NULL DEFAULT '',
  `layer_category` VARCHAR(75) NOT NULL DEFAULT 'management',
  `layer_subcategory` VARCHAR(75) NOT NULL DEFAULT 'aquaculture',
  `layer_kpi_category` ENUM('Energy', 'Ecology', 'Shipping', 'Miscellaneous') NOT NULL DEFAULT 'Miscellaneous',
  `layer_type` TEXT NULL,
  `layer_depth` INT(3) NOT NULL DEFAULT 1,
  `layer_info_properties` TEXT NULL,
  `layer_information` VARCHAR(1024) NULL,
  `layer_text_info` VARCHAR(1024) NOT NULL DEFAULT '{}',
  `layer_states` VARCHAR(255) NULL DEFAULT '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]',
  `layer_raster` VARCHAR(512) NULL,
  `layer_lastupdate` DOUBLE NOT NULL DEFAULT 100,
  `layer_melupdate` TINYINT NOT NULL DEFAULT 0,
  `layer_editing_type` ENUM('cable', 'transformer', 'socket', 'sourcepoint', 'sourcepolygon', 'sourcepolygonpoint', 'multitype', 'protection') NULL,
  `layer_special_entity_type` ENUM('Default', 'ShippingLine') NOT NULL DEFAULT 'Default',
  `layer_green` TINYINT(1) NOT NULL DEFAULT 0,
  `layer_melupdate_construction` TINYINT(1) NOT NULL DEFAULT 0,
  `layer_filecreationtime` DOUBLE NOT NULL DEFAULT 0,
  `layer_media` VARCHAR(255) NULL,
  `layer_entity_value_max` FLOAT NULL,
  PRIMARY KEY (`layer_id`),
  INDEX `fk_layer_layer1_idx` (`layer_original_id` ASC),
  CONSTRAINT `fk_layer_layer1`
    FOREIGN KEY (`layer_original_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `geometry`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `geometry` (
  `geometry_id` INT NOT NULL AUTO_INCREMENT,
  `geometry_layer_id` INT NOT NULL,
  `geometry_persistent` INT NULL,
  `geometry_FID` VARCHAR(75) NULL,
  `geometry_geometry` MEDIUMTEXT CHARACTER SET 'latin1' NULL,
  `geometry_data` TEXT CHARACTER SET 'latin1' NULL COMMENT 'is this format long enough?',
  `geometry_country_id` INT NULL,
  `geometry_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is this geometry active or still valid to become active. This is set to 0 when it is replaced by a new geometry with the same persistent ID when plans are implemented.',
  `geometry_subtractive` INT(11) NULL,
  `geometry_type` VARCHAR(75) NOT NULL DEFAULT 0,
  `geometry_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Has this geometry been deleted by a user? This only applies to geometry inside a plan that hasn\'t become active. E.g. geometry is created in plan, plan is submitted to server, user deletes geometry from said plan, geometry_deleted = 1.',
  `geometry_mspid` VARCHAR(16) NULL,
  PRIMARY KEY (`geometry_id`, `geometry_layer_id`),
  INDEX `fk_gis_layer1_idx` (`geometry_layer_id` ASC),
  INDEX `geometry_persistent` (`geometry_persistent` ASC),
  INDEX `fk_geometry_country1_idx` (`geometry_country_id` ASC),
  UNIQUE `uq_geometry_data`(`geometry_geometry`, `geometry_data`, `geometry_layer_id`),
  CONSTRAINT `fk_gis_layer1`
    FOREIGN KEY (`geometry_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_geometry_country1`
    FOREIGN KEY (`geometry_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `game`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `game` (
  `game_id` INT NOT NULL AUTO_INCREMENT,
  `game_start` INT(5) NULL DEFAULT 2010 COMMENT 'starting year',
  `game_state` ENUM('SETUP', 'PLAY', 'SIMULATION', 'FASTFORWARD', 'PAUSE', 'END') NULL DEFAULT 'SETUP',
  `game_lastupdate` DOUBLE NULL DEFAULT 0,
  `game_currentmonth` INT NULL DEFAULT -1,
  `game_energyupdate` TINYINT NULL DEFAULT 0,
  `game_planning_gametime` INT NULL DEFAULT 36 COMMENT 'how many in-game months the planning phase takes',
  `game_planning_realtime` INT NULL DEFAULT 1 COMMENT 'how long the planning era takes',
  `game_planning_era_realtime` VARCHAR(256) NULL DEFAULT 0 COMMENT 'thie game_planning_realtime for all eras. A comma separated list',
  `game_planning_monthsdone` INT NULL DEFAULT 0 COMMENT 'amount of months done in this part of the era (planning or simulation)',
  `game_eratime` INT NULL DEFAULT 120 COMMENT 'how long the entire era takes (default: 10 years)',
  `game_mel_lastmonth` INT NULL DEFAULT -1,
  `game_cel_lastmonth` INT NULL DEFAULT -1,
  `game_sel_lastmonth` INT NULL DEFAULT -1,
  `game_configfile` VARCHAR(128) NULL,
  `game_autosave_month_interval` INT NULL DEFAULT 120,
  `game_is_running_update` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`game_id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `plan_layer`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan_layer` (
  `plan_layer_id` INT NOT NULL AUTO_INCREMENT,
  `plan_layer_plan_id` INT NOT NULL,
  `plan_layer_layer_id` INT NOT NULL,
  `plan_layer_state` VARCHAR(50) NULL DEFAULT 'WAIT',
  PRIMARY KEY (`plan_layer_id`),
  INDEX `fk_plan_layer_plan1_idx` (`plan_layer_plan_id` ASC),
  INDEX `fk_plan_layer_layer1_idx` (`plan_layer_layer_id` ASC),
  CONSTRAINT `fk_plan_layer_plan1`
    FOREIGN KEY (`plan_layer_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_plan_layer_layer1`
    FOREIGN KEY (`plan_layer_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `plan_message`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan_message` (
  `plan_message_id` INT NOT NULL AUTO_INCREMENT,
  `plan_message_plan_id` INT NOT NULL,
  `plan_message_country_id` INT NOT NULL,
  `plan_message_user_name` VARCHAR(128) NULL,
  `plan_message_text` VARCHAR(512) NULL,
  `plan_message_time` DOUBLE NULL,
  PRIMARY KEY (`plan_message_id`, `plan_message_plan_id`),
  INDEX `fk_plan_message_plan1_idx` (`plan_message_plan_id` ASC),
  INDEX `fk_plan_message_country1_idx` (`plan_message_country_id` ASC),
  CONSTRAINT `fk_plan_message_plan1`
    FOREIGN KEY (`plan_message_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_plan_message_country1`
    FOREIGN KEY (`plan_message_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `event_log`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_log` (
  `event_log_id` INT NOT NULL AUTO_INCREMENT,
  `event_log_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `event_log_source` VARCHAR(75) NOT NULL COMMENT 'What triggered this (Server, MEL, SEL, CEL, Game)?',
  `event_log_severity` ENUM('Warning', 'Error', 'Fatal') NOT NULL DEFAULT 'Warning',
  `event_log_message` TEXT NOT NULL,
  `event_log_stack_trace` TEXT NULL,
  PRIMARY KEY (`event_log_id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mel_layer`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mel_layer` (
  `mel_layer_id` INT NOT NULL AUTO_INCREMENT,
  `mel_layer_pressurelayer` INT NOT NULL,
  `mel_layer_layer_id` INT NOT NULL,
  PRIMARY KEY (`mel_layer_id`),
  INDEX `fk_mel_layer_layer1_idx` (`mel_layer_pressurelayer` ASC),
  INDEX `fk_mel_layer_layer2_idx` (`mel_layer_layer_id` ASC),
  CONSTRAINT `fk_mel_layer_layer1`
    FOREIGN KEY (`mel_layer_pressurelayer`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_mel_layer_layer2`
    FOREIGN KEY (`mel_layer_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `energy_connection`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `energy_connection` (
  `energy_connection_start_id` INT NOT NULL COMMENT 'Database ID of the start geometry\n',
  `energy_connection_end_id` INT NOT NULL COMMENT 'Database ID of the end geometry\n',
  `energy_connection_cable_id` INT NOT NULL COMMENT 'Database ID of the cable geometry\n',
  `energy_connection_start_coordinates` VARCHAR(255) NULL,
  `energy_connection_lastupdate` DOUBLE NULL,
  `energy_connection_active` TINYINT NULL DEFAULT 1,
  INDEX `fk_energy_connection_geometry1_idx` (`energy_connection_start_id` ASC),
  INDEX `fk_energy_connection_geometry2_idx` (`energy_connection_end_id` ASC),
  INDEX `fk_energy_connection_geometry3_idx` (`energy_connection_cable_id` ASC),
  CONSTRAINT `fk_energy_connection_geometry1`
    FOREIGN KEY (`energy_connection_start_id`)
    REFERENCES `geometry` (`geometry_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_energy_connection_geometry2`
    FOREIGN KEY (`energy_connection_end_id`)
    REFERENCES `geometry` (`geometry_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_energy_connection_geometry3`
    FOREIGN KEY (`energy_connection_cable_id`)
    REFERENCES `geometry` (`geometry_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `grid`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grid` (
  `grid_id` INT NOT NULL AUTO_INCREMENT,
  `grid_name` VARCHAR(75) NULL,
  `grid_lastupdate` DOUBLE NULL,
  `grid_active` TINYINT NULL DEFAULT 1,
  `grid_plan_id` INT NOT NULL,
  `grid_persistent` INT NULL,
  `grid_distribution_only` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`grid_id`),
  INDEX `fk_grid_plan1_idx` (`grid_plan_id` ASC),
  INDEX `fk_grid_persistent_index` (`grid_persistent` ASC),
  CONSTRAINT `fk_grid_plan1`
    FOREIGN KEY (`grid_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `energy_output`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `energy_output` (
  `energy_output_id` INT NOT NULL AUTO_INCREMENT,
  `energy_output_geometry_id` INT NOT NULL,
  `energy_output_capacity` VARCHAR(20) NOT NULL DEFAULT 0 COMMENT 'Actually 64bit integer field. ',
  `energy_output_maxcapacity` VARCHAR(20) NOT NULL DEFAULT 0 COMMENT 'Actually 64bit integer field. ',
  `energy_output_lastupdate` DOUBLE NULL DEFAULT 0,
  `energy_output_active` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`energy_output_id`, `energy_output_geometry_id`),
  UNIQUE INDEX `fk_energy_output_geometry1_idx` (`energy_output_geometry_id` ASC),
  CONSTRAINT `fk_energy_output_geometry1`
    FOREIGN KEY (`energy_output_geometry_id`)
    REFERENCES `geometry` (`geometry_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `grid_energy`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grid_energy` (
  `grid_energy_id` INT NOT NULL AUTO_INCREMENT,
  `grid_energy_grid_id` INT NOT NULL,
  `grid_energy_country_id` INT NOT NULL,
  `grid_energy_expected` VARCHAR(20) NULL DEFAULT 0 COMMENT 'Actually 64bit integer field. ',
  PRIMARY KEY (`grid_energy_id`, `grid_energy_grid_id`, `grid_energy_country_id`),
  INDEX `fk_grid_energy_grid1_idx` (`grid_energy_grid_id` ASC),
  INDEX `fk_grid_energy_country1_idx` (`grid_energy_country_id` ASC),
  CONSTRAINT `fk_grid_energy_grid1`
    FOREIGN KEY (`grid_energy_grid_id`)
    REFERENCES `grid` (`grid_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_grid_energy_country1`
    FOREIGN KEY (`grid_energy_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `grid_socket`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grid_socket` (
  `grid_socket_grid_id` INT NOT NULL,
  `grid_socket_geometry_id` INT NOT NULL,
  PRIMARY KEY (`grid_socket_grid_id`, `grid_socket_geometry_id`),
  INDEX `fk_grid_socket_geometry1_idx` (`grid_socket_geometry_id` ASC),
  CONSTRAINT `fk_grid_socket_grid1`
    FOREIGN KEY (`grid_socket_grid_id`)
    REFERENCES `grid` (`grid_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_grid_socket_geometry1`
    FOREIGN KEY (`grid_socket_geometry_id`)
    REFERENCES `geometry` (`geometry_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `fishing`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `fishing` (
  `fishing_id` INT NOT NULL AUTO_INCREMENT,
  `fishing_country_id` INT NOT NULL,
  `fishing_plan_id` INT NOT NULL,
  `fishing_type` VARCHAR(75) NULL,
  `fishing_amount` FLOAT NULL,
  `fishing_active` TINYINT(1) NULL DEFAULT 0 COMMENT 'this is set to active when the plan is implemented',
  INDEX `fk_fishing_plan1_idx` (`fishing_plan_id` ASC),
  PRIMARY KEY (`fishing_id`),
  CONSTRAINT `fk_fishing_country1`
    FOREIGN KEY (`fishing_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_fishing_plan1`
    FOREIGN KEY (`fishing_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `restriction`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `restriction` (
  `restriction_id` INT NOT NULL AUTO_INCREMENT,
  `restriction_start_layer_id` INT NOT NULL,
  `restriction_start_layer_type` VARCHAR(45) NULL,
  `restriction_sort` ENUM('INCLUSION', 'EXCLUSION', 'TYPE_UNAVAILABLE') NOT NULL DEFAULT 'INCLUSION',
  `restriction_type` ENUM('ERROR', 'WARNING', 'INFO') NOT NULL,
  `restriction_message` VARCHAR(512) NULL,
  `restriction_end_layer_id` INT NULL,
  `restriction_end_layer_type` VARCHAR(45) NULL,
  `restriction_value` FLOAT NULL DEFAULT 0,
  PRIMARY KEY (`restriction_id`),
  INDEX `fk_restriction_layer1_idx` (`restriction_start_layer_id` ASC),
  INDEX `fk_restriction_layer2_idx` (`restriction_end_layer_id` ASC),
  CONSTRAINT `fk_restriction_layer1`
    FOREIGN KEY (`restriction_start_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_restriction_layer2`
    FOREIGN KEY (`restriction_end_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `kpi`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kpi` (
  `kpi_id` INT NOT NULL AUTO_INCREMENT,
  `kpi_name` VARCHAR(127) NULL,
  `kpi_value` FLOAT NULL,
  `kpi_month` INT NULL,
  `kpi_type` ENUM('ECOLOGY', 'ENERGY', 'SHIPPING') NULL,
  `kpi_lastupdate` DOUBLE NULL,
  `kpi_unit` VARCHAR(45) NULL,
  `kpi_country_id` INT NOT NULL DEFAULT -1,
  PRIMARY KEY (`kpi_id`),
  INDEX `index_kpi_typ` (`kpi_type` ASC),
  INDEX `index_kpi_lastupdate` (`kpi_lastupdate` ASC),
  UNIQUE INDEX `unique_kpi` (`kpi_name` ASC, `kpi_month` ASC, `kpi_country_id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `warning`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `warning` (
  `warning_id` INT NOT NULL AUTO_INCREMENT,
  `warning_last_update` DOUBLE NULL,
  `warning_active` TINYINT(1) NULL DEFAULT 1,
  `warning_layer_id` INT NOT NULL,
  `warning_issue_type` ENUM('Error', 'Warning', 'Info', 'None') NULL,
  `warning_x` FLOAT NULL,
  `warning_y` FLOAT NULL,
  `warning_source_plan_id` INT NOT NULL,
  `warning_restriction_id` INT NOT NULL,
  PRIMARY KEY (`warning_id`),
  INDEX `fk_warning_layer1_idx` (`warning_layer_id` ASC),
  INDEX `fk_warning_plan1_idx` (`warning_source_plan_id` ASC),
  INDEX `fk_warning_restriction1_idx` (`warning_restriction_id` ASC),
  CONSTRAINT `fk_warning_layer1`
    FOREIGN KEY (`warning_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_warning_plan1`
    FOREIGN KEY (`warning_source_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_warning_restriction1`
    FOREIGN KEY (`warning_restriction_id`)
    REFERENCES `restriction` (`restriction_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `plan_delete`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan_delete` (
  `plan_delete_plan_id` INT NOT NULL,
  `plan_delete_geometry_persistent` INT NULL,
  `plan_delete_layer_id` INT NOT NULL,
  INDEX `fk_plan_delete_layer1_idx` (`plan_delete_layer_id` ASC),
  CONSTRAINT `fk_plan_delete_plan1`
    FOREIGN KEY (`plan_delete_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_plan_delete_layer1`
    FOREIGN KEY (`plan_delete_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `grid_source`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grid_source` (
  `grid_source_grid_id` INT NOT NULL,
  `grid_source_geometry_id` INT NOT NULL,
  PRIMARY KEY (`grid_source_grid_id`, `grid_source_geometry_id`),
  INDEX `fk_grid_source_grid1_idx` (`grid_source_grid_id` ASC),
  CONSTRAINT `fk_grid_source_geometry1`
    FOREIGN KEY (`grid_source_geometry_id`)
    REFERENCES `geometry` (`geometry_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_grid_source_grid1`
    FOREIGN KEY (`grid_source_grid_id`)
    REFERENCES `grid` (`grid_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `grid_removed`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grid_removed` (
  `grid_removed_plan_id` INT NOT NULL,
  `grid_removed_grid_persistent` INT NOT NULL,
  INDEX `fk_table1_plan1_idx` (`grid_removed_plan_id` ASC),
  CONSTRAINT `fk_table1_plan1`
    FOREIGN KEY (`grid_removed_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `objective`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `objective` (
  `objective_id` INT NOT NULL AUTO_INCREMENT,
  `objective_country_id` INT NOT NULL,
  `objective_title` VARCHAR(128) NOT NULL,
  `objective_description` VARCHAR(1024) NOT NULL,
  `objective_deadline` INT NOT NULL COMMENT 'always the in-game month',
  `objective_lastupdate` DOUBLE NOT NULL,
  `objective_active` TINYINT(1) NOT NULL DEFAULT 1,
  `objective_complete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`objective_id`),
  INDEX `fk_objective_country1_idx` (`objective_country_id` ASC),
  CONSTRAINT `fk_objective_country1`
    FOREIGN KEY (`objective_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `task`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `task` (
  `task_id` INT NOT NULL AUTO_INCREMENT,
  `task_objective_id` INT NOT NULL,
  `task_sectorname` VARCHAR(45) NULL,
  `task_category` VARCHAR(75) NULL,
  `task_subcategory` VARCHAR(75) NULL,
  `task_function` VARCHAR(10) NULL,
  `task_value` FLOAT NULL,
  `task_description` VARCHAR(1024) NULL,
  PRIMARY KEY (`task_id`),
  INDEX `fk_task_objective1_idx` (`task_objective_id` ASC),
  CONSTRAINT `fk_task_objective1`
    FOREIGN KEY (`task_objective_id`)
    REFERENCES `objective` (`objective_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `approval`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `approval` (
  `approval_plan_id` INT NOT NULL,
  `approval_country_id` INT NOT NULL,
  `approval_vote` INT NULL DEFAULT -1,
  INDEX `fk_table1_plan2_idx` (`approval_plan_id` ASC),
  INDEX `fk_approval_plan_id_country1_idx` (`approval_country_id` ASC),
  UNIQUE `fk_approval_plan_id_country1_UNIQUE` (`approval_plan_id`, `approval_country_id`),
  CONSTRAINT `fk_table1_plan2`
    FOREIGN KEY (`approval_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_approval_plan_id_country1`
    FOREIGN KEY (`approval_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `energy_kpi`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `energy_kpi` (
  `energy_kpi_id` INT NOT NULL AUTO_INCREMENT,
  `energy_kpi_grid_id` INT NOT NULL,
  `energy_kpi_month` INT NULL,
  `energy_kpi_country_id` INT NOT NULL,
  `energy_kpi_actual` VARCHAR(20) NOT NULL DEFAULT 0 COMMENT 'Actually a long value. ',
  `energy_kpi_lastupdate` DOUBLE NULL,
  PRIMARY KEY (`energy_kpi_id`),
  INDEX `fk_energy_kpi_grid1_idx` (`energy_kpi_grid_id` ASC),
  INDEX `fk_energy_kpi_country1_idx` (`energy_kpi_country_id` ASC),
  UNIQUE INDEX `UNIQUE` (`energy_kpi_month` ASC, `energy_kpi_grid_id` ASC, `energy_kpi_country_id` ASC),
  CONSTRAINT `fk_energy_kpi_grid1`
    FOREIGN KEY (`energy_kpi_grid_id`)
    REFERENCES `grid` (`grid_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_energy_kpi_country1`
    FOREIGN KEY (`energy_kpi_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `plan_restriction_area`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan_restriction_area` (
  `plan_restriction_area_plan_id` INT NOT NULL,
  `plan_restriction_area_layer_id` INT NOT NULL,
  `plan_restriction_area_country_id` INT NOT NULL,
  `plan_restriction_area_entity_type` INT NOT NULL,
  `plan_restriction_area_size` FLOAT NOT NULL,
  INDEX `fk_plan_restriction_area_plan1_idx` (`plan_restriction_area_plan_id` ASC),
  INDEX `fk_plan_restriction_area_layer1_idx` (`plan_restriction_area_layer_id` ASC),
  UNIQUE INDEX `fk_plan_restriction_area_primary_key` (`plan_restriction_area_plan_id` ASC, `plan_restriction_area_layer_id` ASC, `plan_restriction_area_country_id` ASC, `plan_restriction_area_entity_type` ASC),
  INDEX `fk_plan_restriction_area_country1_idx` (`plan_restriction_area_country_id` ASC),
  CONSTRAINT `fk_plan_restriction_area_plan1`
    FOREIGN KEY (`plan_restriction_area_plan_id`)
    REFERENCES `plan` (`plan_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_plan_restriction_area_layer1`
    FOREIGN KEY (`plan_restriction_area_layer_id`)
    REFERENCES `layer` (`layer_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_plan_restriction_area_country1`
    FOREIGN KEY (`plan_restriction_area_country_id`)
    REFERENCES `country` (`country_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shipping_warning`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `shipping_warning` (
  `shipping_warning_id` INT NOT NULL AUTO_INCREMENT,
  `shipping_warning_lastupdate` DOUBLE NOT NULL,
  `shipping_warning_source_geometry_persistent_id` INT NOT NULL,
  `shipping_warning_destination_geometry_persistent_id` INT NOT NULL,
  `shipping_warning_message` VARCHAR(128) NULL,
  `shipping_warning_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`shipping_warning_id`),
  INDEX `fk_source_geometry_idx` (`shipping_warning_source_geometry_persistent_id` ASC),
  INDEX `fk_destination_geometry_idx` (`shipping_warning_destination_geometry_persistent_id` ASC),
  UNIQUE INDEX `unique` (`shipping_warning_source_geometry_persistent_id` ASC, `shipping_warning_destination_geometry_persistent_id` ASC),
  CONSTRAINT `fk_source_geometry`
    FOREIGN KEY (`shipping_warning_source_geometry_persistent_id`)
    REFERENCES `geometry` (`geometry_persistent`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_destination_geometry`
    FOREIGN KEY (`shipping_warning_destination_geometry_persistent_id`)
    REFERENCES `geometry` (`geometry_persistent`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `game_session`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_session` (
  `game_session_watchdog_address` VARCHAR(255) NOT NULL DEFAULT '',
  `game_session_watchdog_token` BIGINT UNSIGNED NOT NULL,
  `game_session_password_admin` TEXT NOT NULL,
  `game_session_password_player` TEXT NOT NULL)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `game_session_api_version`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_session_api_version` (
  `game_session_api_version_server` VARCHAR(16) NOT NULL,
  PRIMARY KEY (`game_session_api_version_server`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `api_token`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_token` (
  `api_token_id` INT NOT NULL AUTO_INCREMENT,
  `api_token_token` BIGINT UNSIGNED NOT NULL COMMENT 'UUID_SHORT()',
  `api_token_valid_until` TIMESTAMP NOT NULL,
  `api_token_scope` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`api_token_id`),
  UNIQUE INDEX `api_token_token_UNIQUE` (`api_token_token` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `api_batch`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_batch` (
  `api_batch_id` int NOT NULL AUTO_INCREMENT,
  `api_batch_state` enum('Setup','Queued','Executing','Success','Failed') NOT NULL DEFAULT 'Setup',
  `api_batch_country_id` int NOT NULL,
  `api_batch_user_id` int NOT NULL,
  `api_batch_server_id` varchar(255) NULL,
  `api_batch_communicated` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`api_batch_id`),
  KEY `api_batch_country_id` (`api_batch_country_id`),
  KEY `api_batch_user_id` (`api_batch_user_id`),
  CONSTRAINT `api_batch_ibfk_1` FOREIGN KEY (`api_batch_country_id`) REFERENCES `country` (`country_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `api_batch_ibfk_2` FOREIGN KEY (`api_batch_user_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `api_batch_task`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_batch_task` (
  `api_batch_task_id` INT NOT NULL AUTO_INCREMENT,
  `api_batch_task_batch_id` INT NOT NULL,
  `api_batch_task_group` INT NOT NULL,
  `api_batch_task_reference_identifier` VARCHAR(32) NOT NULL COMMENT 'Client defined identifier that can be used to reference the results of this call by later calls.',
  `api_batch_task_api_endpoint` VARCHAR(64) NOT NULL,
  `api_batch_task_api_endpoint_data` MEDIUMTEXT NOT NULL COMMENT 'Json-encoded data send to the endpoint.',
  PRIMARY KEY (`api_batch_task_id`),
  INDEX `fk_batch_id_idx` (`api_batch_task_batch_id` ASC),
  CONSTRAINT `fk_batch_id`
    FOREIGN KEY (`api_batch_task_batch_id`)
    REFERENCES `api_batch` (`api_batch_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
SQL;
        $this->addSql($sql);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE IF EXISTS `api_batch`');
        $this->addSql('DROP TABLE IF EXISTS `api_batch_task`');
        $this->addSql('DROP TABLE IF EXISTS `api_token`');
        $this->addSql('DROP TABLE IF EXISTS `approval`');
        $this->addSql('DROP TABLE IF EXISTS `country`');
        $this->addSql('DROP TABLE IF EXISTS `energy_connection`');
        $this->addSql('DROP TABLE IF EXISTS `energy_kpi`');
        $this->addSql('DROP TABLE IF EXISTS `energy_output`');
        $this->addSql('DROP TABLE IF EXISTS `event_log`');
        $this->addSql('DROP TABLE IF EXISTS `fishing`');
        $this->addSql('DROP TABLE IF EXISTS `game`');
        $this->addSql('DROP TABLE IF EXISTS `game_session`');
        $this->addSql('DROP TABLE IF EXISTS `game_session_api_version`');
        $this->addSql('DROP TABLE IF EXISTS `geometry`');
        $this->addSql('DROP TABLE IF EXISTS `grid`');
        $this->addSql('DROP TABLE IF EXISTS `grid_energy`');
        $this->addSql('DROP TABLE IF EXISTS `grid_removed`');
        $this->addSql('DROP TABLE IF EXISTS `grid_socket`');
        $this->addSql('DROP TABLE IF EXISTS `grid_source`');
        $this->addSql('DROP TABLE IF EXISTS `kpi`');
        $this->addSql('DROP TABLE IF EXISTS `layer`');
        $this->addSql('DROP TABLE IF EXISTS `mel_layer`');
        $this->addSql('DROP TABLE IF EXISTS `objective`');
        $this->addSql('DROP TABLE IF EXISTS `plan`');
        $this->addSql('DROP TABLE IF EXISTS `plan_delete`');
        $this->addSql('DROP TABLE IF EXISTS `plan_layer`');
        $this->addSql('DROP TABLE IF EXISTS `plan_message`');
        $this->addSql('DROP TABLE IF EXISTS `plan_restriction_area`');
        $this->addSql('DROP TABLE IF EXISTS `restriction`');
        $this->addSql('DROP TABLE IF EXISTS `shipping_warning`');
        $this->addSql('DROP TABLE IF EXISTS `task`');
        $this->addSql('DROP TABLE IF EXISTS `user`');
        $this->addSql('DROP TABLE IF EXISTS `warning`');
    }
}
