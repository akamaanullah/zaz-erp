CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
