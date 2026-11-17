-- Expand the existing field for OTPHP's Base32 secret. Existing values are preserved.
ALTER TABLE `User` MODIFY COLUMN `secret` VARCHAR(128) NULL;
