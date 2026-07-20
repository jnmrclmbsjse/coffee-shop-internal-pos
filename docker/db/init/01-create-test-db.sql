-- Runs once, only when the Postgres data volume is first initialized.
-- Creates the dedicated database that the PHPUnit suite runs against
-- (see phpunit.xml). The main app DB (coffee_pos) is created by POSTGRES_DB.
-- For an already-initialized volume, create it manually:
--   docker compose exec db psql -U pos -d coffee_pos -c "CREATE DATABASE coffee_pos_test;"
CREATE DATABASE coffee_pos_test;
