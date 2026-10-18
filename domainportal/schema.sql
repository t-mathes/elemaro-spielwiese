CREATE TABLE users (
  id {PK},
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  verified_at VARCHAR(19) NULL,
  token VARCHAR(64) NULL,
  token_kind VARCHAR(10) NULL,
  token_expires VARCHAR(19) NULL,
  type VARCHAR(10) NOT NULL DEFAULT 'private',
  company VARCHAR(190) NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  street VARCHAR(190) NULL,
  zip VARCHAR(20) NULL,
  city VARCHAR(100) NULL,
  country VARCHAR(2) NULL,
  phone VARCHAR(40) NULL,
  created_at VARCHAR(19) NOT NULL
);
CREATE TABLE tlds (
  tld VARCHAR(30) NOT NULL PRIMARY KEY,
  price_cents INTEGER NOT NULL,
  renew_cents INTEGER NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  sort INTEGER NOT NULL DEFAULT 100
);
CREATE TABLE orders (
  id {PK},
  user_id INTEGER NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  total_cents INTEGER NOT NULL,
  currency VARCHAR(3) NOT NULL,
  paypal_order_id VARCHAR(64) NULL,
  paypal_capture_id VARCHAR(64) NULL,
  billing TEXT NULL,
  created_at VARCHAR(19) NOT NULL,
  paid_at VARCHAR(19) NULL
);
CREATE INDEX idx_orders_user ON orders (user_id);
CREATE UNIQUE INDEX idx_orders_pp ON orders (paypal_order_id);
CREATE TABLE order_items (
  id {PK},
  order_id INTEGER NOT NULL,
  name VARCHAR(63) NOT NULL,
  tld VARCHAR(30) NOT NULL,
  price_cents INTEGER NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  note VARCHAR(255) NULL
);
CREATE INDEX idx_items_order ON order_items (order_id);
CREATE TABLE domains (
  id {PK},
  user_id INTEGER NOT NULL,
  order_id INTEGER NULL,
  name VARCHAR(63) NOT NULL,
  tld VARCHAR(30) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  registered_at VARCHAR(19) NOT NULL,
  expires_at VARCHAR(19) NOT NULL,
  auto_renew INTEGER NOT NULL DEFAULT 1,
  locked INTEGER NOT NULL DEFAULT 1,
  auth_code VARCHAR(64) NULL,
  nameservers TEXT NOT NULL,
  contact TEXT NOT NULL
);
CREATE UNIQUE INDEX idx_domains_fqdn ON domains (name, tld);
CREATE INDEX idx_domains_user ON domains (user_id);
CREATE TABLE dns_records (
  id {PK},
  domain_id INTEGER NOT NULL,
  type VARCHAR(10) NOT NULL,
  host VARCHAR(190) NOT NULL,
  value VARCHAR(500) NOT NULL,
  ttl INTEGER NOT NULL DEFAULT 3600,
  prio INTEGER NULL
);
CREATE INDEX idx_dns_domain ON dns_records (domain_id);
