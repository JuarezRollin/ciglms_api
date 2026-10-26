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

 Date: 29/01/2026 10:18:28
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for psgc_regions
-- ----------------------------
DROP TABLE IF EXISTS `psgc_regions`;
CREATE TABLE `psgc_regions`  (
  `reg_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_correspondence` char(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '9-digit PSGC code',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `altName` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `code` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '10-digit PSGC code',
  `geo_level` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'Reg',
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `archive` tinyint(1) NULL DEFAULT NULL,
  PRIMARY KEY (`reg_id`) USING BTREE,
  UNIQUE INDEX `uk_code_correspondence`(`code_correspondence` ASC) USING BTREE,
  UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
  INDEX `idx_name`(`name` ASC) USING BTREE,
  INDEX `idx_altName`(`altName` ASC) USING BTREE,
  INDEX `idx_geo_level`(`geo_level` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of psgc_regions
-- ----------------------------
INSERT INTO `psgc_regions` VALUES (1, '010000000', 'Region I (Ilocos Region)', 'I', '0100000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (2, '020000000', 'Region II (Cagayan Valley)', 'II', '0200000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (3, '030000000', 'Region III (Central Luzon)', 'III', '0300000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (4, '040000000', 'Region IV-A (CALABARZON)', 'IV-A', '0400000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (5, '050000000', 'Region V (Bicol Region)', 'V', '0500000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (6, '060000000', 'Region VI (Western Visayas)', 'VI', '0600000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (7, '070000000', 'Region VII (Central Visayas)', 'VII', '0700000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (8, '080000000', 'Region VIII (Eastern Visayas)', 'VIII', '0800000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (9, '090000000', 'Region IX (Zamboanga Peninsula)', 'IX', '0900000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (10, '100000000', 'Region X (Northern Mindanao)', 'X', '1000000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (11, '110000000', 'Region XI (Davao Region)', 'XI', '1100000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (12, '120000000', 'Region XII (SOCCSKSARGEN)', 'XII', '1200000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (13, '130000000', 'National Capital Region (NCR)', 'NCR', '1300000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (14, '140000000', 'Cordillera Administrative Region (CAR)', 'CAR', '1400000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (16, '160000000', 'Region XIII (Caraga)', 'XIII', '1600000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (17, '170000000', 'MIMAROPA Region', 'IV-B', '1700000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (18, '180000000', 'Negros Island Region (NIR)', 'NIR', '1800000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (19, '150000000', 'Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)', 'BARMM', '1900000000', 'Reg', NULL, 1);
INSERT INTO `psgc_regions` VALUES (20, '000000000', 'Central Office', 'CO', '0000000000', 'Reg', NULL, 1);

SET FOREIGN_KEY_CHECKS = 1;
