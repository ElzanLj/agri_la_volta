-- 0001 Initial schema.
--
-- Apply with: php bin/migrate.php
-- Or, on hosting without command line access, import this file in phpMyAdmin;
-- the last statement records the migration so bin/migrate.php will skip it.
--
-- Conventions:
-- * Stay intervals are [check_in, check_out): the check-out day is free for the next guest.
--   Blocks and seasonal rates use the same half-open convention [start_date, end_date).
-- * Money is stored as integer cents (EUR).
-- * DATETIME columns hold UTC.
-- * Statements end with ";" at the end of a line (required by bin/migrate.php).
-- * No business data (prices, rules, company details) is inserted here.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single shared administrator account (SPEC §14). Created only via bin/create-admin.php.
CREATE TABLE admin (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Apartments (SPEC §4, §12). Unknown details stay NULL until the owner provides them.
CREATE TABLE apartments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    -- 'direct' = managed by the farm; 'agency' = temporarily managed by an agency (e.g. Novasol).
    management_mode VARCHAR(20) NOT NULL DEFAULT 'direct',
    managing_agency VARCHAR(100) NULL,
    -- Whether the public request form offers this apartment; switched from the admin panel.
    accepts_online_requests TINYINT(1) NOT NULL DEFAULT 1,
    max_guests TINYINT UNSIGNED NULL,
    bedrooms TINYINT UNSIGNED NULL,
    beds TINYINT UNSIGNED NULL,
    check_in_from TIME NULL,
    check_in_until TIME NULL,
    check_out_until TIME NULL,
    -- "From" price shown on public pages only; never used to compute a stay total.
    indicative_price_cents INT UNSIGNED NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_apartments_slug (slug),
    CONSTRAINT chk_apartments_management_mode CHECK (management_mode IN ('direct', 'agency'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Separate IT/EN content per apartment (SPEC §4, §20).
CREATE TABLE apartment_translations (
    apartment_id INT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    description TEXT NULL,
    rules TEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(300) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (apartment_id, locale),
    CONSTRAINT fk_apartment_translations_apartment FOREIGN KEY (apartment_id)
        REFERENCES apartments (id) ON DELETE CASCADE,
    CONSTRAINT chk_apartment_translations_locale CHECK (locale IN ('it', 'en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public stay requests (SPEC §8–10). They never block availability by themselves.
CREATE TABLE booking_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Public reference shown to the guest and used in emails.
    reference VARCHAR(20) NOT NULL,
    apartment_id INT UNSIGNED NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    adults TINYINT UNSIGNED NOT NULL,
    children TINYINT UNSIGNED NOT NULL DEFAULT 0,
    pets TINYINT UNSIGNED NOT NULL DEFAULT 0,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    locale CHAR(2) NOT NULL DEFAULT 'it',
    -- Total recalculated server-side at submission; NULL while no rates are configured.
    quoted_total_cents INT UNSIGNED NULL,
    -- JSON snapshot of the server-side price calculation shown in the summary.
    price_breakdown TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    privacy_accepted_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_requests_reference (reference),
    KEY idx_booking_requests_status_created (status, created_at),
    KEY idx_booking_requests_apartment_dates (apartment_id, check_in, check_out),
    KEY idx_booking_requests_check_in (check_in),
    CONSTRAINT fk_booking_requests_apartment FOREIGN KEY (apartment_id)
        REFERENCES apartments (id) ON DELETE RESTRICT,
    CONSTRAINT chk_booking_requests_dates CHECK (check_out > check_in),
    CONSTRAINT chk_booking_requests_adults CHECK (adults >= 1),
    CONSTRAINT chk_booking_requests_status CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled')),
    CONSTRAINT chk_booking_requests_locale CHECK (locale IN ('it', 'en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Confirmed stays from any channel (SPEC §10–11, §16). Only 'confirmed' rows occupy dates.
-- Overlap between confirmed bookings of the same apartment is prevented in the
-- application inside a transaction that locks the apartment row (decision P6).
CREATE TABLE bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    apartment_id INT UNSIGNED NOT NULL,
    -- Set when the booking comes from an approved website request.
    booking_request_id INT UNSIGNED NULL,
    origin VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
    children TINYINT UNSIGNED NOT NULL DEFAULT 0,
    pets TINYINT UNSIGNED NOT NULL DEFAULT 0,
    -- Free text: manual bookings may only have a name or an agency reference.
    guest_name VARCHAR(200) NOT NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(40) NULL,
    total_cents INT UNSIGNED NULL,
    notes TEXT NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bookings_booking_request (booking_request_id),
    KEY idx_bookings_availability (apartment_id, status, check_in, check_out),
    KEY idx_bookings_check_in (check_in),
    CONSTRAINT fk_bookings_apartment FOREIGN KEY (apartment_id)
        REFERENCES apartments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_booking_request FOREIGN KEY (booking_request_id)
        REFERENCES booking_requests (id) ON DELETE RESTRICT,
    CONSTRAINT chk_bookings_dates CHECK (check_out > check_in),
    CONSTRAINT chk_bookings_origin CHECK (origin IN ('website', 'phone', 'email', 'agency', 'novasol', 'other')),
    CONSTRAINT chk_bookings_status CHECK (status IN ('confirmed', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dates closed by the admin (maintenance, private use, ...). Removing a block deletes the row
-- and is recorded in audit_log.
CREATE TABLE availability_blocks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    apartment_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_availability_blocks_apartment_dates (apartment_id, start_date, end_date),
    CONSTRAINT fk_availability_blocks_apartment FOREIGN KEY (apartment_id)
        REFERENCES apartments (id) ON DELETE CASCADE,
    CONSTRAINT chk_availability_blocks_dates CHECK (end_date > start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nightly rate per apartment and period (SPEC §5). Guest/pet variations, supplements and
-- other rules are added in Phase 2B. Values are entered by the admin, never seeded.
CREATE TABLE seasonal_rates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    apartment_id INT UNSIGNED NOT NULL,
    label VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    nightly_rate_cents INT UNSIGNED NOT NULL,
    min_nights TINYINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_seasonal_rates_apartment_dates (apartment_id, start_date, end_date),
    CONSTRAINT fk_seasonal_rates_apartment FOREIGN KEY (apartment_id)
        REFERENCES apartments (id) ON DELETE CASCADE,
    CONSTRAINT chk_seasonal_rates_dates CHECK (end_date > start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- History of relevant changes (SPEC §15). Values are JSON text; no actor (single admin).
CREATE TABLE audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    summary VARCHAR(255) NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_log_entity (entity_type, entity_id),
    KEY idx_audit_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting for login and public forms (SPEC §28–29). Keys are hashed, rows kept 24 h.
CREATE TABLE rate_limit_hits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(50) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_limit_hits_lookup (bucket, key_hash, created_at),
    KEY idx_rate_limit_hits_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('0001_initial_schema');
