CREATE TABLE IF NOT EXISTS events (
    source_name VARCHAR(190) NOT NULL,
    event_id BIGINT NOT NULL,
    payload JSONB NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    PRIMARY KEY (source_name, event_id)
);

CREATE TABLE IF NOT EXISTS event_inbox (
    source_name VARCHAR(190) NOT NULL,
    event_id BIGINT NOT NULL,
    payload JSONB NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    PRIMARY KEY (source_name, event_id)
);

CREATE TABLE IF NOT EXISTS source_cursor (
    source_name VARCHAR(190) PRIMARY KEY,
    last_requested_id BIGINT NOT NULL DEFAULT 0,
    last_stored_id BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS source_rate_limit (
    source_name VARCHAR(190) PRIMARY KEY,
    last_request_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS event_request_log (
    id BIGSERIAL PRIMARY KEY,
    source_name VARCHAR(190) NOT NULL,
    after_id BIGINT NOT NULL,
    limit_count INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    max_id BIGINT NULL,
    error TEXT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    UNIQUE (source_name, after_id)
);
