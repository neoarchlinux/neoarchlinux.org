CREATE TABLE components (
    id SERIAL PRIMARY KEY,
    name TEXT UNIQUE NOT NULL,
    is_virtual BOOLEAN NOT NULL
);

CREATE INDEX idx_components_name ON components(name);
