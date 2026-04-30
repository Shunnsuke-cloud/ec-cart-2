-- Add shipping notification timestamp to orders
ALTER TABLE orders
ADD COLUMN shipping_notified_at DATETIME DEFAULT NULL;
