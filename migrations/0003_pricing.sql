-- 0003 Pricing structure (docs/PLAN.md, Phase 2B).
--
-- STRUCTURE ONLY: this migration inserts no prices, seasons, rules or supplements.
-- The real price list is entered by the owner from the admin panel (docs/MISSING_DATA.md).
--
-- Model:
-- * seasonal_rates: base price per apartment per night for a period [start_date, end_date).
-- * pricing_rules: additive charges (extra adults, children, pets, flat supplements).
--   apartment_id NULL = applies to every apartment.
-- * Money is integer cents (EUR). No percentages: they need rounding rules and, for
--   children, ages, which the request form does not collect.

ALTER TABLE apartments
    ADD COLUMN max_children TINYINT UNSIGNED NULL AFTER max_guests,
    ADD COLUMN max_pets TINYINT UNSIGNED NULL AFTER max_children;

ALTER TABLE seasonal_rates
    ADD COLUMN label_en VARCHAR(100) NULL AFTER label;

CREATE TABLE pricing_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    apartment_id INT UNSIGNED NULL,
    -- adult | child | pet: charged per unit above free_units; stay: one flat charge.
    applies_to VARCHAR(10) NOT NULL,
    -- per_night: amount x units x nights; per_stay: amount x units once.
    charge_basis VARCHAR(10) NOT NULL,
    -- Units not charged (e.g. adults included in the base rate). 0 for 'stay'.
    free_units TINYINT UNSIGNED NOT NULL DEFAULT 0,
    amount_cents INT UNSIGNED NOT NULL,
    -- Optional validity window [valid_from, valid_to). per_night counts the nights inside it;
    -- per_stay applies when the check-in date is inside it.
    valid_from DATE NULL,
    valid_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    label_it VARCHAR(100) NOT NULL,
    label_en VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pricing_rules_lookup (is_active, apartment_id),
    CONSTRAINT fk_pricing_rules_apartment FOREIGN KEY (apartment_id)
        REFERENCES apartments (id) ON DELETE CASCADE,
    CONSTRAINT chk_pricing_rules_applies_to CHECK (applies_to IN ('adult', 'child', 'pet', 'stay')),
    CONSTRAINT chk_pricing_rules_basis CHECK (charge_basis IN ('per_night', 'per_stay')),
    CONSTRAINT chk_pricing_rules_window CHECK (valid_from IS NULL OR valid_to IS NULL OR valid_to > valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('0003_pricing');
