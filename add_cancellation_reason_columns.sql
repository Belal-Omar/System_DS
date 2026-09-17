-- Add cancellation and return reason columns to orders table
ALTER TABLE orders
ADD COLUMN cancellation_reason TEXT DEFAULT NULL,
ADD COLUMN return_reason TEXT DEFAULT NULL;
