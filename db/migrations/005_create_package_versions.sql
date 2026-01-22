CREATE TABLE IF NOT EXISTS package_versions (
    id SERIAL PRIMARY KEY,

    package_id INT NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
    repo_id INT NOT NULL REFERENCES repos(id) ON DELETE CASCADE,
    arch_id INT NOT NULL REFERENCES arches(id) ON DELETE CASCADE,

    uploaded_by INT REFERENCES users(id) ON DELETE SET NULL,

    package_version TEXT NOT NULL,
    package_release TEXT NOT NULL,

    file_name TEXT NOT NULL,
    checksum_sha256 TEXT NOT NULL,
    size_bytes BIGINT NOT NULL,

    overwritten BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),

    UNIQUE(package_id, repo_id, arch_id, package_version, package_release)
);

CREATE INDEX IF NOT EXISTS idx_package_versions_repo_arch ON package_versions(repo_id, arch_id);
CREATE INDEX IF NOT EXISTS idx_package_versions_repo_arch_package ON package_versions(repo_id, arch_id, package_id);
CREATE INDEX IF NOT EXISTS idx_package_versions_package ON package_versions(package_id);
