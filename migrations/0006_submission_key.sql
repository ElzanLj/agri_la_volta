-- 0006 Idempotent public submission (review finding A2).
--
-- The public form sends a unique key with each submission: a hash of the form token and of the data
-- sent. A second POST with the same key (double click, resend from a slow phone) finds the request
-- already stored and gets the same reference, without a new request or a new e-mail.
-- The key is NULL for requests that did not come from the public form and is cleared when the
-- request is anonymised (it is derived from the guest's data). UNIQUE allows many NULLs.

ALTER TABLE booking_requests
    ADD COLUMN submission_key CHAR(64) NULL AFTER reference,
    ADD UNIQUE KEY uq_booking_requests_submission_key (submission_key);

INSERT IGNORE INTO schema_migrations (version) VALUES ('0006_submission_key');
