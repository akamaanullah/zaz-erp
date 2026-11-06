ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(255) NULL AFTER is_active,
  ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret,
  ADD COLUMN totp_last_step INT UNSIGNED NOT NULL DEFAULT 0 AFTER totp_enabled;

CREATE TABLE totp_recovery_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_totp_recovery_user (user_id),
  CONSTRAINT fk_totp_recovery_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
