-- fix_order_status_values.sql
-- Run this against your store database to normalize Order_Status values.

UPDATE `order`
SET Order_Status = 'Shipped'
WHERE Order_Status IN ('Shipping', 'Shippped');

UPDATE `order`
SET Order_Status = 'Delivered'
WHERE Order_Status IN ('Complete', 'Completed');

-- Optional: normalize any remaining legacy values if needed
UPDATE `order`
SET Order_Status = 'Pending'
WHERE Order_Status = 'Pending';

UPDATE `order`
SET Order_Status = 'Processing'
WHERE Order_Status = 'Processing';

-- If you also use a lower-case status variant, normalize them too.
UPDATE `order`
SET Order_Status = 'Pending'
WHERE LOWER(Order_Status) = 'pending' AND Order_Status != 'Pending';

UPDATE `order`
SET Order_Status = 'Processing'
WHERE LOWER(Order_Status) = 'processing' AND Order_Status != 'Processing';

UPDATE `order`
SET Order_Status = 'Shipped'
WHERE LOWER(Order_Status) IN ('shipping', 'shipped') AND Order_Status != 'Shipped';

UPDATE `order`
SET Order_Status = 'Delivered'
WHERE LOWER(Order_Status) IN ('complete', 'completed', 'delivered') AND Order_Status != 'Delivered';
