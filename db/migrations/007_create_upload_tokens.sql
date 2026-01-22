CREATE TABLE IF NOT EXISTS upload_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    signing_key_fingerprint TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_upload_tokens_user ON upload_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_upload_tokens_token ON upload_tokens(token_hash);
