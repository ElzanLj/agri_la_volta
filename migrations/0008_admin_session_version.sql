-- 0008 Admin session version (prompt 17, "Esci da tutti i dispositivi").
--
-- Every admin session carries the version it was created with. Raising the number in the database
-- ends all the other sessions on their next request, without changing the password. Changing the
-- password already ends them (a session is also bound to a fingerprint of the password hash); this
-- number is for the case "close everything but keep the password". No personal data is stored here.

ALTER TABLE admin
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_hash;

INSERT IGNORE INTO schema_migrations (version) VALUES ('0008_admin_session_version');
