-- Fix Cab Bookings Provider ID
-- This script updates all cab bookings to be linked to the transport service provider

-- Update all cab bookings with provider_id = 0 to link to the transport service provider (ID 1)
UPDATE cab_bookings 
SET provider_id = 1 
WHERE provider_id = 0 OR provider_id IS NULL;

-- Verify the update
SELECT COUNT(*) as updated_bookings FROM cab_bookings WHERE provider_id = 1; 