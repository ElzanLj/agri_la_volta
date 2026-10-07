-- 0007 Database constraints for impossible states (review findings C12-C15).
--
-- The application already refuses these states; the database now refuses them too, so a bug or a
-- manual edit in phpMyAdmin cannot leave them behind. Limits match the technical limits of the form
-- (GuestCounts): adults 1-20, children 0-20, pets 0-10.
--
-- Before this file runs, bin/migrate.php checks that the existing rows already respect every rule
-- (see App\Database\MigrationPreconditions). If one does not, the migration stops with a clear message
-- BEFORE changing anything. Importing this file by hand in phpMyAdmin skips that check: the ALTER
-- statements still fail on offending rows, but earlier tables may already have been changed.
--
-- Needs MySQL 8.0.16+ or MariaDB 10.2+ (earlier MySQL versions accept CHECK but ignore it).

ALTER TABLE bookings
    ADD CONSTRAINT chk_bookings_cancelled_at CHECK (
        (status = 'cancelled' AND cancelled_at IS NOT NULL) OR (status <> 'cancelled' AND cancelled_at IS NULL)
    ),
    ADD CONSTRAINT chk_bookings_guests CHECK (adults BETWEEN 1 AND 20 AND children <= 20 AND pets <= 10);

ALTER TABLE booking_requests
    ADD CONSTRAINT chk_booking_requests_decided_at CHECK (
        (status = 'pending' AND decided_at IS NULL) OR (status <> 'pending' AND decided_at IS NOT NULL)
    ),
    ADD CONSTRAINT chk_booking_requests_guests CHECK (adults BETWEEN 1 AND 20 AND children <= 20 AND pets <= 10);

ALTER TABLE email_outbox
    ADD CONSTRAINT chk_email_outbox_sent_at CHECK (status <> 'sent' OR sent_at IS NOT NULL);

INSERT IGNORE INTO schema_migrations (version) VALUES ('0007_data_constraints');
