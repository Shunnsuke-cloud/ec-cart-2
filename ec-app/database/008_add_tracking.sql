-- Add tracking number and shipped_at to orders
ALTER TABLE orders
ADD COLUMN tracking_number VARCHAR(128) DEFAULT NULL,
ADD COLUMN shipped_at DATETIME DEFAULT NULL;

-- Optional index on tracking_number for lookups
CREATE INDEX IF NOT EXISTS idx_orders_tracking_number ON orders (tracking_number(64));
