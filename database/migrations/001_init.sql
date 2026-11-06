CREATE TABLE schema_migrations (
  version VARCHAR(191) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE farms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  acres DECIMAL(10,2) NOT NULL DEFAULT 0,
  seri_acres DECIMAL(10,2) NOT NULL DEFAULT 0,
  location VARCHAR(120) NOT NULL DEFAULT '',
  purchase_date DATE NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_farms_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seasons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(40) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seasons_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  username VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','office','supervisor','accountant') NOT NULL,
  farm_id INT UNSIGNED NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  pay_type VARCHAR(40) NOT NULL DEFAULT 'Monthly salary',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_farm (farm_id),
  CONSTRAINT fk_users_farm FOREIGN KEY (farm_id) REFERENCES farms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  role ENUM('kamdar') NOT NULL DEFAULT 'kamdar',
  farm_id INT UNSIGNED NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  pay_type VARCHAR(40) NOT NULL DEFAULT 'Monthly salary',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_staff_farm (farm_id),
  CONSTRAINT fk_staff_farm FOREIGN KEY (farm_id) REFERENCES farms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE haris (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  address VARCHAR(160) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_haris_farm (farm_id),
  CONSTRAINT fk_haris_farm FOREIGN KEY (farm_id) REFERENCES farms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  hari_id INT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  acres DECIMAL(10,2) NOT NULL,
  arrangement ENUM('hari','seri') NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_plots_hari (hari_id),
  KEY idx_plots_farm (farm_id),
  CONSTRAINT fk_plots_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_plots_hari FOREIGN KEY (hari_id) REFERENCES haris (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crop_allocations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plot_id INT UNSIGNED NOT NULL,
  hari_id INT UNSIGNED NULL,
  season_id INT UNSIGNED NOT NULL,
  crop VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plot_season_crop (plot_id, season_id, crop),
  CONSTRAINT fk_alloc_plot FOREIGN KEY (plot_id) REFERENCES plots (id),
  CONSTRAINT fk_alloc_hari FOREIGN KEY (hari_id) REFERENCES haris (id),
  CONSTRAINT fk_alloc_season FOREIGN KEY (season_id) REFERENCES seasons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  unit VARCHAR(40) NOT NULL,
  default_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  hari_share TINYINT UNSIGNED NOT NULL DEFAULT 50,
  is_stock TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE godown_receipts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_name VARCHAR(40) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  rate DECIMAL(12,2) NOT NULL,
  received_on DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_receipts_item (item_name, id),
  CONSTRAINT fk_receipts_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(40) NOT NULL,
  qty_sent DECIMAL(12,2) NOT NULL,
  qty_received DECIMAL(12,2) NULL,
  sent_on DATE NOT NULL,
  received_by INT UNSIGNED NULL,
  received_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_transfers_farm (farm_id, item_name),
  CONSTRAINT fk_transfers_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_transfers_user FOREIGN KEY (created_by) REFERENCES users (id),
  CONSTRAINT fk_transfers_receiver FOREIGN KEY (received_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_usages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(40) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  rate_snapshot DECIMAL(12,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  target_type ENUM('hari','seri') NOT NULL,
  hari_id INT UNSIGNED NULL,
  crop VARCHAR(80) NOT NULL,
  share_snapshot TINYINT UNSIGNED NOT NULL,
  status ENUM('pending','approved') NOT NULL,
  stock_deducted TINYINT(1) NOT NULL DEFAULT 1,
  by_label VARCHAR(40) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_usage_farm (farm_id, item_name),
  CONSTRAINT fk_usage_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_usage_season FOREIGN KEY (season_id) REFERENCES seasons (id),
  CONSTRAINT fk_usage_hari FOREIGN KEY (hari_id) REFERENCES haris (id),
  CONSTRAINT fk_usage_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  category_name VARCHAR(60) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  rate_snapshot DECIMAL(12,2) NOT NULL,
  unit_snapshot VARCHAR(40) NOT NULL DEFAULT '',
  amount DECIMAL(14,2) NOT NULL,
  target_type ENUM('hari','seri') NOT NULL,
  hari_id INT UNSIGNED NULL,
  crop VARCHAR(80) NOT NULL,
  share_snapshot TINYINT UNSIGNED NOT NULL,
  status ENUM('pending','approved') NOT NULL,
  by_label VARCHAR(40) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_expenses_farm (farm_id),
  CONSTRAINT fk_expenses_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_expenses_season FOREIGN KEY (season_id) REFERENCES seasons (id),
  CONSTRAINT fk_expenses_hari FOREIGN KEY (hari_id) REFERENCES haris (id),
  CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  crop VARCHAR(80) NOT NULL,
  target_type ENUM('hari','seri') NOT NULL,
  hari_id INT UNSIGNED NULL,
  weight_unit ENUM('maund','kg') NOT NULL,
  weight DECIMAL(12,2) NOT NULL,
  rate_snapshot DECIMAL(12,2) NOT NULL,
  kg DECIMAL(14,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  hari_share_amount DECIMAL(14,2) NOT NULL,
  owner_share_amount DECIMAL(14,2) NOT NULL,
  status ENUM('pending','approved') NOT NULL,
  by_label VARCHAR(40) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sales_farm (farm_id),
  CONSTRAINT fk_sales_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_sales_season FOREIGN KEY (season_id) REFERENCES seasons (id),
  CONSTRAINT fk_sales_hari FOREIGN KEY (hari_id) REFERENCES haris (id),
  CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  season_id INT UNSIGNED NOT NULL,
  hari_id INT UNSIGNED NOT NULL,
  reason VARCHAR(160) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('pending','approved') NOT NULL,
  by_label VARCHAR(40) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_advances_farm (farm_id),
  CONSTRAINT fk_advances_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_advances_season FOREIGN KEY (season_id) REFERENCES seasons (id),
  CONSTRAINT fk_advances_hari FOREIGN KEY (hari_id) REFERENCES haris (id),
  CONSTRAINT fk_advances_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_work (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farm_id INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  occurred_at DATETIME NOT NULL,
  work_type VARCHAR(80) NOT NULL,
  description VARCHAR(500) NOT NULL,
  resources VARCHAR(160) NOT NULL DEFAULT '',
  acres DECIMAL(10,2) NULL,
  hours DECIMAL(8,2) NULL,
  notes VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_daily_farm (farm_id, occurred_at),
  CONSTRAINT fk_daily_farm FOREIGN KEY (farm_id) REFERENCES farms (id),
  CONSTRAINT fk_daily_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salary_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  staff_id INT UNSIGNED NULL,
  pay_month CHAR(7) NOT NULL,
  kind ENUM('Salary','Advance') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Paid','Pending') NOT NULL,
  pay_date DATE NOT NULL,
  note VARCHAR(160) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_salary_month (pay_month),
  CONSTRAINT fk_salary_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_salary_staff FOREIGN KEY (staff_id) REFERENCES staff (id),
  CONSTRAINT fk_salary_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_settings (
  user_id INT UNSIGNED NOT NULL,
  theme VARCHAR(10) NOT NULL DEFAULT 'light',
  language VARCHAR(5) NOT NULL DEFAULT 'en',
  PRIMARY KEY (user_id),
  CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  username VARCHAR(30) NOT NULL,
  farm_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  message VARCHAR(500) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_farm (farm_id),
  KEY idx_audit_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER audit_log_no_update
BEFORE UPDATE ON audit_log
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit log is append-only';

CREATE TRIGGER audit_log_no_delete
BEFORE DELETE ON audit_log
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit log is append-only';
