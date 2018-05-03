-- Valentina Studio --
-- MySQL dump --
-- ---------------------------------------------------------


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_area" --------------------------------
-- CREATE TABLE "mrbs_area" ------------------------------------
CREATE TABLE `mrbs_area` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`disabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`area_name` VarChar( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`sort_key` VarChar( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`timezone` VarChar( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`area_admin_email` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`resolution` Int( 11 ) NULL,
	`default_duration` Int( 11 ) NULL,
	`default_duration_all_day` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`morningstarts` Int( 11 ) NULL,
	`morningstarts_minutes` Int( 11 ) NULL,
	`eveningends` Int( 11 ) NULL,
	`eveningends_minutes` Int( 11 ) NULL,
	`private_enabled` TinyInt( 1 ) NULL,
	`private_default` TinyInt( 1 ) NULL,
	`private_mandatory` TinyInt( 1 ) NULL,
	`private_override` VarChar( 32 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`min_create_ahead_enabled` TinyInt( 1 ) NULL,
	`min_create_ahead_secs` Int( 11 ) NULL,
	`max_create_ahead_enabled` TinyInt( 1 ) NULL,
	`max_create_ahead_secs` Int( 11 ) NULL,
	`min_delete_ahead_enabled` TinyInt( 1 ) NULL,
	`min_delete_ahead_secs` Int( 11 ) NULL,
	`max_delete_ahead_enabled` TinyInt( 1 ) NULL,
	`max_delete_ahead_secs` Int( 11 ) NULL,
	`max_per_day_enabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`max_per_day` Int( 11 ) NOT NULL DEFAULT '0',
	`max_per_week_enabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`max_per_week` Int( 11 ) NOT NULL DEFAULT '0',
	`max_per_month_enabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`max_per_month` Int( 11 ) NOT NULL DEFAULT '0',
	`max_per_year_enabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`max_per_year` Int( 11 ) NOT NULL DEFAULT '0',
	`max_per_future_enabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`max_per_future` Int( 11 ) NOT NULL DEFAULT '0',
	`max_duration_enabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`max_duration_secs` Int( 11 ) NOT NULL DEFAULT '0',
	`max_duration_periods` Int( 11 ) NOT NULL DEFAULT '0',
	`custom_html` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`approval_enabled` TinyInt( 1 ) NULL,
	`reminders_enabled` TinyInt( 1 ) NULL,
	`enable_periods` TinyInt( 1 ) NULL,
	`periods` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`confirmation_enabled` TinyInt( 1 ) NULL,
	`confirmed_default` TinyInt( 1 ) NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `uq_area_name` UNIQUE( `area_name` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 3;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_entry" -------------------------------
-- CREATE TABLE "mrbs_entry" -----------------------------------
CREATE TABLE `mrbs_entry` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`start_time` Int( 11 ) NOT NULL DEFAULT '0',
	`end_time` Int( 11 ) NOT NULL DEFAULT '0',
	`entry_type` Int( 11 ) NOT NULL DEFAULT '0',
	`repeat_id` Int( 11 ) NULL,
	`room_id` Int( 11 ) NOT NULL DEFAULT '1',
	`timestamp` Timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	`create_by` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`modified_by` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`name` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`type` Char( 1 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'E',
	`description` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`status` TinyInt( 3 ) UNSIGNED NOT NULL DEFAULT '0',
	`reminded` Int( 11 ) NULL,
	`info_time` Int( 11 ) NULL,
	`info_user` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`info_text` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`ical_uid` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`ical_sequence` Smallint( 6 ) NOT NULL DEFAULT '0',
	`ical_recur_id` VarChar( 16 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 129;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_repeat" ------------------------------
-- CREATE TABLE "mrbs_repeat" ----------------------------------
CREATE TABLE `mrbs_repeat` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`start_time` Int( 11 ) NOT NULL DEFAULT '0',
	`end_time` Int( 11 ) NOT NULL DEFAULT '0',
	`rep_type` Int( 11 ) NOT NULL DEFAULT '0',
	`end_date` Int( 11 ) NOT NULL DEFAULT '0',
	`rep_opt` VarChar( 32 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`room_id` Int( 11 ) NOT NULL DEFAULT '1',
	`timestamp` Timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	`create_by` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`modified_by` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`name` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`type` Char( 1 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'E',
	`description` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`rep_num_weeks` Smallint( 6 ) NULL,
	`month_absolute` Smallint( 6 ) NULL,
	`month_relative` VarChar( 4 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`status` TinyInt( 3 ) UNSIGNED NOT NULL DEFAULT '0',
	`reminded` Int( 11 ) NULL,
	`info_time` Int( 11 ) NULL,
	`info_user` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`info_text` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`ical_uid` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`ical_sequence` Smallint( 6 ) NOT NULL DEFAULT '0',
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 1;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_room" --------------------------------
-- CREATE TABLE "mrbs_room" ------------------------------------
CREATE TABLE `mrbs_room` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`disabled` TinyInt( 1 ) NOT NULL DEFAULT '0',
	`area_id` Int( 11 ) NOT NULL DEFAULT '0',
	`room_name` VarChar( 25 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`sort_key` VarChar( 25 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`description` VarChar( 60 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`capacity` Int( 11 ) NOT NULL DEFAULT '0',
	`room_admin_email` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`custom_html` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `uq_room_name` UNIQUE( `area_id`, `room_name` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 4;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_users" -------------------------------
-- CREATE TABLE "mrbs_users" -----------------------------------
CREATE TABLE `mrbs_users` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`level` Smallint( 6 ) NOT NULL DEFAULT '0',
	`name` VarChar( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`password_hash` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`email` VarChar( 75 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`timestamp` Timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	`phone` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`group` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `uq_name` UNIQUE( `name` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 15;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_variables" ---------------------------
-- CREATE TABLE "mrbs_variables" -------------------------------
CREATE TABLE `mrbs_variables` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`variable_name` VarChar( 80 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`variable_content` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `uq_variable_name` UNIQUE( `variable_name` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 3;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE TABLE "mrbs_zoneinfo" ----------------------------
-- CREATE TABLE "mrbs_zoneinfo" --------------------------------
CREATE TABLE `mrbs_zoneinfo` ( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`timezone` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`outlook_compatible` TinyInt( 3 ) UNSIGNED NOT NULL DEFAULT '0',
	`vtimezone` Text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
	`last_updated` Int( 11 ) NOT NULL DEFAULT '0',
	PRIMARY KEY ( `id` ),
	CONSTRAINT `uq_timezone` UNIQUE( `timezone`, `outlook_compatible` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 2;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE INDEX "idxEndTime" -------------------------------
-- CREATE INDEX "idxEndTime" -----------------------------------
CREATE INDEX `idxEndTime` USING BTREE ON `mrbs_entry`( `end_time` );
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE INDEX "idxStartTime" -----------------------------
-- CREATE INDEX "idxStartTime" ---------------------------------
CREATE INDEX `idxStartTime` USING BTREE ON `mrbs_entry`( `start_time` );
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE INDEX "repeat_id" --------------------------------
-- CREATE INDEX "repeat_id" ------------------------------------
CREATE INDEX `repeat_id` USING BTREE ON `mrbs_entry`( `repeat_id` );
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE INDEX "room_id" ----------------------------------
-- CREATE INDEX "room_id" --------------------------------------
CREATE INDEX `room_id` USING BTREE ON `mrbs_entry`( `room_id` );
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE INDEX "room_id" ----------------------------------
-- CREATE INDEX "room_id" --------------------------------------
CREATE INDEX `room_id` USING BTREE ON `mrbs_repeat`( `room_id` );
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE INDEX "idxSortKey" -------------------------------
-- CREATE INDEX "idxSortKey" -----------------------------------
CREATE INDEX `idxSortKey` USING BTREE ON `mrbs_room`( `sort_key` );
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE LINK "mrbs_entry_ibfk_1" -------------------------
-- CREATE LINK "mrbs_entry_ibfk_1" -----------------------------
ALTER TABLE `mrbs_entry`
	ADD CONSTRAINT `mrbs_entry_ibfk_1` FOREIGN KEY ( `room_id` )
	REFERENCES `mrbs_room`( `id` )
	ON DELETE Restrict
	ON UPDATE Cascade;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE LINK "mrbs_entry_ibfk_2" -------------------------
-- CREATE LINK "mrbs_entry_ibfk_2" -----------------------------
ALTER TABLE `mrbs_entry`
	ADD CONSTRAINT `mrbs_entry_ibfk_2` FOREIGN KEY ( `repeat_id` )
	REFERENCES `mrbs_repeat`( `id` )
	ON DELETE Cascade
	ON UPDATE Cascade;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE LINK "mrbs_repeat_ibfk_1" ------------------------
-- CREATE LINK "mrbs_repeat_ibfk_1" ----------------------------
ALTER TABLE `mrbs_repeat`
	ADD CONSTRAINT `mrbs_repeat_ibfk_1` FOREIGN KEY ( `room_id` )
	REFERENCES `mrbs_room`( `id` )
	ON DELETE Restrict
	ON UPDATE Cascade;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


-- CREATE LINK "mrbs_room_ibfk_1" --------------------------
-- CREATE LINK "mrbs_room_ibfk_1" ------------------------------
ALTER TABLE `mrbs_room`
	ADD CONSTRAINT `mrbs_room_ibfk_1` FOREIGN KEY ( `area_id` )
	REFERENCES `mrbs_area`( `id` )
	ON DELETE Restrict
	ON UPDATE Cascade;
-- -------------------------------------------------------------
-- ---------------------------------------------------------


/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- ---------------------------------------------------------


