CREATE TABLE IF NOT EXISTS event_request_log_archive (
    LIKE event_request_log INCLUDING ALL
) PARTITION BY RANGE (created_at);

-- Example monthly partition (adjust as needed)
CREATE TABLE IF NOT EXISTS event_request_log_archive_2026_01
    PARTITION OF event_request_log_archive
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');

CREATE INDEX IF NOT EXISTS event_request_log_archive_2026_01_status_idx
    ON event_request_log_archive_2026_01 (status, created_at);