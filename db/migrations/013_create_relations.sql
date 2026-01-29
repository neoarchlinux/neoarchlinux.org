CREATE TYPE relation_type AS ENUM (
    'DEPENDS',
    'OPTDEPENDS',
    'MAKEDEPENDS',
    'CHECKDEPENDS',
    'PROVIDES',
    'CONFLICTS',
    'REPLACES'
);

CREATE TABLE package_relations (
    id SERIAL PRIMARY KEY,
    package_id INT NOT NULL REFERENCES package_meta(id) ON DELETE CASCADE,
    component_id INT NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    relation_type relation_type NOT NULL,
    version_expr TEXT,
    relation_description TEXT
);

CREATE UNIQUE INDEX uniq_package_relation ON package_relations(package_id, component_id, relation_type, version_expr);

CREATE INDEX idx_package_relations_package ON package_relations(package_id);
CREATE INDEX idx_package_relations_component ON package_relations(component_id);
CREATE INDEX idx_package_relations_type ON package_relations(relation_type);
