-- 0002 The six apartments listed in docs/SPEC.md §4.
-- Only names and URL slugs are known. Capacity, rooms, beds, times, prices and
-- descriptions stay NULL until the owner provides them (docs/MISSING_DATA.md).

INSERT INTO apartments (slug, name, sort_order) VALUES
    ('margherita', 'Margherita', 1),
    ('girasole', 'Girasole', 2),
    ('rosa', 'Rosa', 3),
    ('mimosa', 'Mimosa', 4),
    ('ciclamino', 'Ciclamino', 5),
    ('viola', 'Viola', 6);

INSERT IGNORE INTO schema_migrations (version) VALUES ('0002_seed_apartments');
