ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `contact_name`     varchar(150) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contact_phone`    varchar(50)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contact_facebook` varchar(150) DEFAULT NULL;

-- Backfill existing bookings from the customer record as it is TODAY.
-- (Anything already overwritten in the past cannot be recovered.)
UPDATE `bookings` b
JOIN `customers` c ON c.customer_id = b.customer_id
SET b.contact_name     = COALESCE(b.contact_name, c.full_name),
    b.contact_phone    = COALESCE(b.contact_phone, c.phone),
    b.contact_facebook = COALESCE(b.contact_facebook, c.facebook_name)
WHERE b.contact_name IS NULL OR b.contact_phone IS NULL OR b.contact_facebook IS NULL;