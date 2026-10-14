-- Runs once on an empty data directory (dev). Migrations also create these
-- extensions so managed PostgreSQL services get them too.
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
