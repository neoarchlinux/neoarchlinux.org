CREATE TABLE IF NOT EXISTS package_history (
    id SERIAL PRIMARY KEY,
    package_version_id INT NOT NULL REFERENCES package_versions(id) ON DELETE CASCADE,
    source_ip INET NOT NULL,
    user_agent TEXT NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_package_history_pkg_version ON package_history(package_version_id);
