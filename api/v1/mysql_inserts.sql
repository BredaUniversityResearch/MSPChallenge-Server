-- -----------------------------------------------------
-- Data for table `msp`.`game`
-- -----------------------------------------------------
START TRANSACTION;
USE `msp`;
INSERT INTO `msp`.`game` (`game_id`, `game_start`, `game_lastupdate`, `game_currentmonth`, `game_energyupdate`, `game_planning_gametime`, `game_planning_realtime`, `game_planning_monthsdone`, `game_eratime`) VALUES (1, YEAR(CURDATE()), 0, 0, 0, 36, 10800, 0, 120);

COMMIT;

