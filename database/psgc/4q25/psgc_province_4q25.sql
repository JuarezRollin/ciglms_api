/*
 Navicat Premium Data Transfer

 Source Server         : localhost_3306
 Source Server Type    : MySQL
 Source Server Version : 80030
 Source Host           : localhost:3306
 Source Schema         : psgc_q4_2025

 Target Server Type    : MySQL
 Target Server Version : 80030
 File Encoding         : 65001

 Date: 29/01/2026 10:18:41
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for psgc_provinces
-- ----------------------------
DROP TABLE IF EXISTS `psgc_provinces`;
CREATE TABLE `psgc_provinces`  (
  `prov_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_correspondence` char(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '9-digit PSGC code',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '10-digit PSGC code',
  `geo_level` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `old_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `income_classification` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `region` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '10-digit region code',
  `region_correspondence` char(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '9-digit region PSGC',
  `reg_id` int UNSIGNED NOT NULL,
  `archive` tinyint(1) NULL DEFAULT NULL,
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`prov_id`) USING BTREE,
  UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
  UNIQUE INDEX `uk_code_correspondence`(`code_correspondence` ASC) USING BTREE,
  INDEX `idx_name`(`name` ASC) USING BTREE,
  INDEX `idx_region`(`region` ASC) USING BTREE,
  INDEX `idx_region_correspondence`(`region_correspondence` ASC) USING BTREE,
  INDEX `idx_reg_id`(`reg_id` ASC) USING BTREE,
  CONSTRAINT `fk_province_region` FOREIGN KEY (`reg_id`) REFERENCES `psgc_region_4q25` (`reg_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 88 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of psgc_provinces
-- ----------------------------
INSERT INTO `psgc_provinces` VALUES (1, '012800000', 'Ilocos Norte', '0102800000', 'Prov', NULL, '1st', '0100000000', '010000000', 1, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (2, '012900000', 'Ilocos Sur', '0102900000', 'Prov', NULL, '1st', '0100000000', '010000000', 1, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (3, '013300000', 'La Union', '0103300000', 'Prov', NULL, '1st', '0100000000', '010000000', 1, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (4, '015500000', 'Pangasinan', '0105500000', 'Prov', NULL, '1st', '0100000000', '010000000', 1, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (5, '020900000', 'Batanes', '0200900000', 'Prov', NULL, '5th', '0200000000', '020000000', 2, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (6, '021500000', 'Cagayan', '0201500000', 'Prov', NULL, '1st', '0200000000', '020000000', 2, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (7, '023100000', 'Isabela', '0203100000', 'Prov', NULL, '1st', '0200000000', '020000000', 2, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (8, '025000000', 'Nueva Vizcaya', '0205000000', 'Prov', NULL, '2nd', '0200000000', '020000000', 2, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (9, '025700000', 'Quirino', '0205700000', 'Prov', NULL, '3rd', '0200000000', '020000000', 2, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (10, '030800000', 'Bataan', '0300800000', 'Prov', NULL, '1st', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (11, '031400000', 'Bulacan', '0301400000', 'Prov', NULL, '1st', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (12, '034900000', 'Nueva Ecija', '0304900000', 'Prov', NULL, '1st', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (13, '035400000', 'Pampanga', '0305400000', 'Prov', NULL, '1st', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (14, '036900000', 'Tarlac', '0306900000', 'Prov', NULL, '1st', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (15, '037100000', 'Zambales', '0307100000', 'Prov', NULL, '2nd', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (16, '037700000', 'Aurora', '0307700000', 'Prov', NULL, '3rd', '0300000000', '030000000', 3, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (17, '041000000', 'Batangas', '0401000000', 'Prov', NULL, '1st', '0400000000', '040000000', 4, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (18, '042100000', 'Cavite', '0402100000', 'Prov', NULL, '1st', '0400000000', '040000000', 4, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (19, '043400000', 'Laguna', '0403400000', 'Prov', NULL, '1st', '0400000000', '040000000', 4, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (20, '045600000', 'Quezon', '0405600000', 'Prov', NULL, '1st', '0400000000', '040000000', 4, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (21, '045800000', 'Rizal', '0405800000', 'Prov', NULL, '1st', '0400000000', '040000000', 4, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (22, '050500000', 'Albay', '0500500000', 'Prov', NULL, '1st', '0500000000', '050000000', 5, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (23, '051600000', 'Camarines Norte', '0501600000', 'Prov', NULL, '2nd', '0500000000', '050000000', 5, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (24, '051700000', 'Camarines Sur', '0501700000', 'Prov', NULL, '1st', '0500000000', '050000000', 5, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (25, '052000000', 'Catanduanes', '0502000000', 'Prov', NULL, '3rd', '0500000000', '050000000', 5, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (26, '054100000', 'Masbate', '0504100000', 'Prov', NULL, '1st', '0500000000', '050000000', 5, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (27, '056200000', 'Sorsogon', '0506200000', 'Prov', NULL, '2nd', '0500000000', '050000000', 5, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (28, '060400000', 'Aklan', '0600400000', 'Prov', NULL, '2nd', '0600000000', '060000000', 6, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (29, '060600000', 'Antique', '0600600000', 'Prov', NULL, '2nd', '0600000000', '060000000', 6, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (30, '061900000', 'Capiz', '0601900000', 'Prov', NULL, '1st', '0600000000', '060000000', 6, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (31, '063000000', 'Iloilo', '0603000000', 'Prov', NULL, '1st', '0600000000', '060000000', 6, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (32, '067900000', 'Guimaras', '0607900000', 'Prov', NULL, '4th', '0600000000', '060000000', 6, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (33, '071200000', 'Bohol', '0701200000', 'Prov', NULL, '1st', '0700000000', '070000000', 7, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (34, '072200000', 'Cebu', '0702200000', 'Prov', NULL, '1st', '0700000000', '070000000', 7, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (35, '082600000', 'Eastern Samar', '0802600000', 'Prov', NULL, '2nd', '0800000000', '080000000', 8, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (36, '083700000', 'Leyte', '0803700000', 'Prov', NULL, '1st', '0800000000', '080000000', 8, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (37, '084800000', 'Northern Samar', '0804800000', 'Prov', NULL, '2nd', '0800000000', '080000000', 8, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (38, '086000000', 'Samar', '0806000000', 'Prov', 'Western Samar', '1st', '0800000000', '080000000', 8, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (39, '086400000', 'Southern Leyte', '0806400000', 'Prov', NULL, '3rd', '0800000000', '080000000', 8, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (40, '087800000', 'Biliran', '0807800000', 'Prov', NULL, '4th', '0800000000', '080000000', 8, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (41, '097200000', 'Zamboanga del Norte', '0907200000', 'Prov', NULL, '1st', '0900000000', '090000000', 9, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (42, '097300000', 'Zamboanga del Sur', '0907300000', 'Prov', NULL, '1st', '0900000000', '090000000', 9, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (43, '098300000', 'Zamboanga Sibugay', '0908300000', 'Prov', NULL, '2nd', '0900000000', '090000000', 9, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (44, '099700000', 'City of Isabela (Not a Province)', '0990100000', NULL, NULL, NULL, '0900000000', '090000000', 9, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (45, '101300000', 'Bukidnon', '1001300000', 'Prov', NULL, '1st', '1000000000', '100000000', 10, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (46, '101800000', 'Camiguin', '1001800000', 'Prov', NULL, '5th', '1000000000', '100000000', 10, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (47, '103500000', 'Lanao del Norte', '1003500000', 'Prov', NULL, '2nd', '1000000000', '100000000', 10, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (48, '104200000', 'Misamis Occidental', '1004200000', 'Prov', NULL, '2nd', '1000000000', '100000000', 10, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (49, '104300000', 'Misamis Oriental', '1004300000', 'Prov', NULL, '1st', '1000000000', '100000000', 10, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (50, '112300000', 'Davao del Norte', '1102300000', 'Prov', NULL, '1st', '1100000000', '110000000', 11, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (51, '112400000', 'Davao del Sur', '1102400000', 'Prov', NULL, '1st', '1100000000', '110000000', 11, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (52, '112500000', 'Davao Oriental', '1102500000', 'Prov', NULL, '1st', '1100000000', '110000000', 11, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (53, '118200000', 'Davao de Oro', '1108200000', 'Prov', 'Compostela Valley', '1st', '1100000000', '110000000', 11, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (54, '118600000', 'Davao Occidental', '1108600000', 'Prov', NULL, '4th', '1100000000', '110000000', 11, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (55, '124700000', 'Cotabato', '1204700000', 'Prov', 'North Cotabato', '1st', '1200000000', '120000000', 12, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (56, '126300000', 'South Cotabato', '1206300000', 'Prov', NULL, '1st', '1200000000', '120000000', 12, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (57, '126500000', 'Sultan Kudarat', '1206500000', 'Prov', NULL, '1st', '1200000000', '120000000', 12, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (58, '128000000', 'Sarangani', '1208000000', 'Prov', NULL, '2nd', '1200000000', '120000000', 12, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (59, '133900000', 'NCR, City of Manila, First District (Not a Province)', '1303900000', 'Dist', NULL, NULL, '1300000000', '130000000', 13, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (60, '137400000', 'NCR, Second District (Not a Province)', '1307400000', 'Dist', NULL, NULL, '1300000000', '130000000', 13, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (61, '137500000', 'NCR, Third District (Not a Province)', '1307500000', 'Dist', NULL, NULL, '1300000000', '130000000', 13, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (62, '137600000', 'NCR, Fourth District (Not a Province)', '1307600000', 'Dist', NULL, NULL, '1300000000', '130000000', 13, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (63, '140100000', 'Abra', '1400100000', 'Prov', NULL, '3rd', '1400000000', '140000000', 14, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (64, '141100000', 'Benguet', '1401100000', 'Prov', NULL, '2nd', '1400000000', '140000000', 14, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (65, '142700000', 'Ifugao', '1402700000', 'Prov', NULL, '3rd', '1400000000', '140000000', 14, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (66, '143200000', 'Kalinga', '1403200000', 'Prov', NULL, '3rd', '1400000000', '140000000', 14, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (67, '144400000', 'Mountain Province', '1404400000', 'Prov', NULL, '4th', '1400000000', '140000000', 14, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (68, '148100000', 'Apayao', '1408100000', 'Prov', NULL, '3rd', '1400000000', '140000000', 14, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (69, '160200000', 'Agusan del Norte', '1600200000', 'Prov', NULL, '3rd', '1600000000', '160000000', 16, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (70, '160300000', 'Agusan del Sur', '1600300000', 'Prov', NULL, '1st', '1600000000', '160000000', 16, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (71, '166700000', 'Surigao del Norte', '1606700000', 'Prov', NULL, '2nd', '1600000000', '160000000', 16, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (72, '166800000', 'Surigao del Sur', '1606800000', 'Prov', NULL, '1st', '1600000000', '160000000', 16, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (73, '168500000', 'Dinagat Islands', '1608500000', 'Prov', NULL, '4th', '1600000000', '160000000', 16, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (74, '174000000', 'Marinduque', '1704000000', 'Prov', NULL, '4th', '1700000000', '170000000', 17, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (75, '175100000', 'Occidental Mindoro', '1705100000', 'Prov', NULL, '2nd', '1700000000', '170000000', 17, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (76, '175200000', 'Oriental Mindoro', '1705200000', 'Prov', NULL, '1st', '1700000000', '170000000', 17, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (77, '175300000', 'Palawan', '1705300000', 'Prov', NULL, '1st', '1700000000', '170000000', 17, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (78, '175900000', 'Romblon', '1705900000', 'Prov', NULL, '3rd', '1700000000', '170000000', 17, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (79, '064500000', 'Negros Occidental', '1804500000', 'Prov', NULL, '1st', '1800000000', '180000000', 18, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (80, '074600000', 'Negros Oriental', '1804600000', 'Prov', NULL, '1st', '1800000000', '180000000', 18, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (81, '076100000', 'Siquijor', '1806100000', 'Prov', NULL, '5th', '1800000000', '180000000', 18, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (82, '150700000', 'Basilan', '1900700000', 'Prov', NULL, '3rd', '1900000000', '150000000', 19, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (83, '153600000', 'Lanao del Sur', '1903600000', 'Prov', NULL, '1st', '1900000000', '150000000', 19, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (84, '156600000', 'Sulu', '0906600000', 'Prov', NULL, '2nd', '0900000000', '150000000', 9, 1, 'Transferred from BARMM to FO IX');
INSERT INTO `psgc_provinces` VALUES (85, '157000000', 'Tawi-Tawi', '1907000000', 'Prov', NULL, '3rd', '1900000000', '150000000', 19, 1, NULL);
INSERT INTO `psgc_provinces` VALUES (86, NULL, 'Maguindanao del Norte', '1908700000', 'Prov', NULL, '1st', '1900000000', '150000000', 19, 1, 'Updated Province Code - 4Q2024');
INSERT INTO `psgc_provinces` VALUES (87, NULL, 'Maguindanao del Sur', '1908800000', 'Prov', NULL, '1st', '1900000000', '150000000', 19, 1, 'Updated Province Code - 4Q2024');
INSERT INTO `psgc_provinces` VALUES (88, NULL, 'Special Geographic Area', '1999900000', NULL, NULL, NULL, '1900000000', '150000000', 19, 1, 'Updated Province Code - 4Q2024');

SET FOREIGN_KEY_CHECKS = 1;
