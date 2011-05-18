TRUNCATE TABLE `urls`;

ALTER TABLE `urls` ADD UNIQUE (`base_url`, `full_url`);