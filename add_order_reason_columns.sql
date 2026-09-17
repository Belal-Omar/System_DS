-- Add cancellation and return reason columns to orders table
ALTER TABLE orders ADD COLUMN cancellation_reason TEXT NULL AFTER status;
ALTER TABLE orders ADD COLUMN return_reason TEXT NULL AFTER cancellation_reason;
ALTER TABLE orders ADD COLUMN reason_type ENUM('cancellation', 'return') NULL AFTER return_reason;
