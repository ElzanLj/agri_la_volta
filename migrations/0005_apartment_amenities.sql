-- 0005 Apartment amenities (SPEC §4, "servizi").
--
-- One plain-text field per language, one amenity per line, edited by the admin (Appartamenti).
-- No amenity is inserted here: the owner provides them (docs/MISSING_DATA.md).
--
-- Apply with: php bin/migrate.php
-- Or, on hosting without command line access, import this file in phpMyAdmin;
-- the last statement records the migration so bin/migrate.php will skip it.

ALTER TABLE apartment_translations
    ADD COLUMN amenities TEXT NULL AFTER rules;

INSERT IGNORE INTO schema_migrations (version) VALUES ('0005_apartment_amenities');
