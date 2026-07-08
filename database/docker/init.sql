-- Docker entrypoint init script
-- Creates the test database alongside the main database
CREATE DATABASE IF NOT EXISTS cwt_academy_test;
GRANT ALL PRIVILEGES ON cwt_academy_test.* TO 'cwt'@'%';
FLUSH PRIVILEGES;
