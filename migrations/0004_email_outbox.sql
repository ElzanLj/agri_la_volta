-- 0004 Email outbox (docs/PLAN.md, Phase 4).
--
-- Business events (new request, confirmation, rejection) insert a row here INSIDE the same
-- transaction as the state change. Sending happens afterwards, outside any transaction, so an
-- SMTP failure can never undo or block a booking. Rows hold only identifiers: the message is
-- rendered at send time and no customer data is copied here (the only exception is the
-- admin-edited text of a cancellation email, cleared once it has been sent).

CREATE TABLE email_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- new_request_admin | request_confirmed | request_rejected | cancellation
    type VARCHAR(30) NOT NULL,
    booking_request_id INT UNSIGNED NULL,
    booking_id INT UNSIGNED NULL,
    locale CHAR(2) NOT NULL DEFAULT 'it',
    -- Only for 'cancellation': the text the admin edited and chose to send.
    subject VARCHAR(255) NULL,
    body TEXT NULL,
    -- pending: waiting | sending: claimed by a worker | sent | failed | skipped (nobody to write to)
    status VARCHAR(12) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    -- Short machine code and a sanitised message: never passwords, never e-mail addresses.
    error_code VARCHAR(40) NULL,
    error_message VARCHAR(255) NULL,
    -- 0 = permanent error: no automatic retry (a manual retry is still possible).
    retryable TINYINT(1) NOT NULL DEFAULT 1,
    next_attempt_at DATETIME NULL,
    locked_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_outbox_due (status, next_attempt_at),
    KEY idx_email_outbox_request (booking_request_id),
    KEY idx_email_outbox_booking (booking_id),
    CONSTRAINT fk_email_outbox_request FOREIGN KEY (booking_request_id)
        REFERENCES booking_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_email_outbox_booking FOREIGN KEY (booking_id)
        REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT chk_email_outbox_type CHECK (type IN ('new_request_admin', 'request_confirmed', 'request_rejected', 'cancellation')),
    CONSTRAINT chk_email_outbox_status CHECK (status IN ('pending', 'sending', 'sent', 'failed', 'skipped')),
    CONSTRAINT chk_email_outbox_locale CHECK (locale IN ('it', 'en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('0004_email_outbox');
