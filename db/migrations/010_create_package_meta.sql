CREATE TABLE IF NOT EXISTS package_meta (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    repo TEXT NOT NULL,
    version TEXT NOT NULL,
    description TEXT,
    arch TEXT,
    last_updated DATE,
    CONSTRAINT pkg_meta_name_repo_unique UNIQUE (name, repo)
);

CREATE INDEX IF NOT EXISTS idx_pkg_meta_name ON package_meta(name text_pattern_ops);
CREATE INDEX IF NOT EXISTS idx_pkg_meta_desc ON package_meta(description text_pattern_ops);
CREATE INDEX IF NOT EXISTS idx_pkg_meta_name_trgm ON package_meta USING gin (name gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_pkg_meta_desc_trgm ON package_meta USING gin (description gin_trgm_ops);
