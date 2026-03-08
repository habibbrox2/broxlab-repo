-- ========================================
-- টেবিল / Table: mobile_specs
-- তারিখ / Date: 2026-03-08 02:32:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 12
-- ========================================

CREATE TABLE IF NOT EXISTS `mobile_specs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile_id` int(11) NOT NULL,
  `spec_key` varchar(255) NOT NULL,
  `spec_value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mobile_id` (`mobile_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('1', '1', 'Body', '164.4x77.9x7.5mm, 192g; Glass front (Gorilla Glass Victus), plastic frame, glass fiber back; IP54 dust protected and water resistant (water splashes).');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('2', '1', 'Display', '6.70\" Super AMOLED, 90Hz, 800 nits (HBM), 1080x2340px resolution, 19.5:9 aspect ratio, 385ppi');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('3', '1', 'Chipset', 'Exynos 1330 (5 nm): Octa-core (2x2.4 GHz Cortex-A78 & 6x2.0 GHz Cortex-A55); Mali-G68 MP2.');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('4', '1', 'Memory', '128GB 4GB RAM, 128GB 6GB RAM, 128GB 8GB RAM, 256GB 8GB RAM; microSDXC (uses shared SIM slot)');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('5', '1', 'OS/Software', 'Android 15, up to 6 major Android upgrades, One UI 7.');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('6', '1', 'Rear camera', 'Wide (main): 50 MP, f/1.8, 1/2.76\", 0.64µm, AF, OIS; Ultra wide angle: 5 MP, f/2.2, 1/5.0\", 1.12µm; Macro: 2 MP, f/2.4');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('7', '1', 'Front camera', '3 MP, f/2.0, (wide), 1/3.1\", 1.12µm');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('8', '1', 'Video capture', 'Rear camera: 1080p@30fps, gyro-EIS; Front camera: 1080p@30fps.');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('9', '1', 'Battery', '5000mAh; 25W wired');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('10', '1', 'Connectivity', '5G; eSIM; Wi-Fi 5; BT 5.3; NFC');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('11', '1', 'Misc', 'Fingerprint reader (side-mounted).');
INSERT INTO `mobile_specs` (`id`, `mobile_id`, `spec_key`, `spec_value`) VALUES ('15', '2', 'Battery', '164.4x77.9x7.5mm, 192g; Glass front (Gorilla Glass Victus), plastic frame, glass fiber back; IP54 dust protected and water resistant (water splashes).');
