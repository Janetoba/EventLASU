-- LasuTix — Full Database Setup
-- Run this entire file once in phpMyAdmin or MySQL client

CREATE DATABASE IF NOT EXISTS campus_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_tickets;

CREATE TABLE users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100)  NOT NULL,
  email          VARCHAR(150)  UNIQUE NOT NULL,
  password       VARCHAR(255)  NOT NULL,
  matric_number  VARCHAR(50)   DEFAULT NULL,
  verified       TINYINT(1)    DEFAULT 0,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE email_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  token       VARCHAR(64)  NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used        TINYINT(1)   DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE events (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL,
  title        VARCHAR(200)  NOT NULL,
  description  TEXT,
  venue        VARCHAR(200)  NOT NULL,
  event_date   DATETIME      NOT NULL,
  price        DECIMAL(10,2) DEFAULT 0.00,
  capacity     INT           NOT NULL DEFAULT 100,
  tickets_sold INT           DEFAULT 0,
  category     VARCHAR(50)   DEFAULT 'Other',
  image        VARCHAR(255)  DEFAULT NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE orders (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT           NOT NULL,
  event_id    INT           NOT NULL,
  quantity    INT           DEFAULT 1,
  total_price DECIMAL(10,2) NOT NULL,
  status      ENUM('pending','confirmed','cancelled') DEFAULT 'confirmed',
  ordered_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id)  ON DELETE CASCADE
);

-- Optional: add indexes for performance
CREATE INDEX idx_events_date     ON events(event_date);
CREATE INDEX idx_orders_user     ON orders(user_id);
CREATE INDEX idx_orders_event    ON orders(event_id);
CREATE INDEX idx_tokens_token    ON email_tokens(token);
