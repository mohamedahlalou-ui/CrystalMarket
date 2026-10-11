-- ============================================================================
-- CrystalMarket — database schema
-- Target: MySQL 8.0.16+ (CHECK constraints enforced), InnoDB, utf8mb4.
-- Executed by install.php. The database itself is created/selected by the
-- installer using the credentials supplied by the administrator (no DB name,
-- user or password is stored in this file).
-- Status: DRAFT for Phase 1 review — NOT yet executed against a real MySQL
-- server (none available in the authoring environment). See docs/phase1/REPORT.md.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- migrations
CREATE TABLE schema_migrations (
  version      VARCHAR(32)  NOT NULL,
  applied_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- roles & permissions
CREATE TABLE roles (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(32)  NOT NULL,
  name         VARCHAR(64)  NOT NULL,
  is_system    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(64)  NOT NULL,
  description  VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id        SMALLINT UNSIGNED NOT NULL,
  permission_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY ix_role_permissions_permission (permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------- users
CREATE TABLE users (
  id                 BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  role_id            SMALLINT UNSIGNED NOT NULL,
  email              VARCHAR(190)      NOT NULL,
  display_name       VARCHAR(80)       NOT NULL,
  password_hash      VARCHAR(255)      NOT NULL,            -- password_hash() output only
  status             ENUM('active','restricted','suspended') NOT NULL DEFAULT 'active',
  locale             CHAR(2)           NOT NULL DEFAULT 'fr',
  theme              ENUM('light','dark','green','rose') NOT NULL DEFAULT 'light',
  phone              VARCHAR(32)       NULL,
  avatar_file        VARCHAR(64)       NULL,
  email_verified_at  DATETIME          NULL,
  last_login_at      DATETIME          NULL,
  created_at         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_role (role_id),
  KEY ix_users_status (status),
  KEY ix_users_created (created_at),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
  CONSTRAINT ck_users_locale CHECK (locale IN ('fr','nl','en','es'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64)        NOT NULL,                      -- SHA-256 of the token; the token itself is never stored
  expires_at  DATETIME        NOT NULL,
  used_at     DATETIME        NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prt_token (token_hash),
  KEY ix_prt_user (user_id),
  KEY ix_prt_expires (expires_at),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verification_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64)        NOT NULL,
  expires_at  DATETIME        NOT NULL,
  used_at     DATETIME        NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evt_token (token_hash),
  KEY ix_evt_user (user_id),
  CONSTRAINT fk_evt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic throttling store: login attempts, message sending, reports, resets.
-- key_hash = SHA-256(HMAC(app_secret, identifier or IP)) -> no raw IP / email stored.
CREATE TABLE rate_limits (
  bucket             VARCHAR(40)  NOT NULL,                  -- e.g. login_ip, login_email, message_send
  key_hash           CHAR(64)     NOT NULL,
  hits               INT UNSIGNED NOT NULL DEFAULT 1,
  window_started_at  DATETIME     NOT NULL,
  blocked_until      DATETIME     NULL,
  PRIMARY KEY (bucket, key_hash),
  KEY ix_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- categories
CREATE TABLE categories (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  parent_id      INT UNSIGNED      NULL,
  slug           VARCHAR(80)       NOT NULL,
  icon           VARCHAR(64)       NOT NULL DEFAULT 'tag',   -- key into the local SVG icon sprite
  image_file     VARCHAR(64)       NULL,                     -- optional tile picture (stored in uploads)
  has_condition  TINYINT(1)        NOT NULL DEFAULT 1,       -- show "new / used" for this category
  sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active      TINYINT(1)        NOT NULL DEFAULT 1,
  created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY ix_categories_parent_sort (parent_id, sort_order),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category_translations (
  category_id  INT UNSIGNED NOT NULL,
  locale       CHAR(2)      NOT NULL,
  name         VARCHAR(80)  NOT NULL,
  PRIMARY KEY (category_id, locale),
  CONSTRAINT fk_cattr_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
  CONSTRAINT ck_cattr_locale CHECK (locale IN ('fr','nl','en','es'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ listings
-- Listing text is stored once, in the language written by the seller
-- (per-language listing translations are explicitly out of scope — ADR-006).
CREATE TABLE listings (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  category_id       INT UNSIGNED    NOT NULL,
  title             VARCHAR(140)    NOT NULL,
  slug              VARCHAR(160)    NOT NULL,
  description       TEXT            NOT NULL,
  price             DECIMAL(12,2)   NULL,                    -- NULL when price_type is 'contact'
  currency          CHAR(3)         NOT NULL DEFAULT 'EUR',
  price_type        ENUM('fixed','negotiable','free','contact') NOT NULL DEFAULT 'fixed',
  condition_state   ENUM('new','like_new','used','for_parts') NULL,
  country_code      CHAR(2)         NOT NULL,
  city              VARCHAR(80)     NOT NULL,
  status            ENUM('draft','pending','published','rejected','archived','suspended') NOT NULL DEFAULT 'draft',
  rejection_reason  VARCHAR(500)    NULL,
  is_featured       TINYINT(1)      NOT NULL DEFAULT 0,
  featured_until    DATETIME        NULL,
  views_count       INT UNSIGNED    NOT NULL DEFAULT 0,
  submitted_at      DATETIME        NULL,
  published_at      DATETIME        NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_listings_user_status (user_id, status),
  KEY ix_listings_status_published (status, published_at),
  KEY ix_listings_category_status (category_id, status, published_at),
  KEY ix_listings_city (country_code, city),
  KEY ix_listings_price (price),
  KEY ix_listings_featured (is_featured, featured_until),
  FULLTEXT KEY ft_listings_search (title, description),
  CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_listings_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
  CONSTRAINT ck_listings_price CHECK (price IS NULL OR price >= 0),
  CONSTRAINT ck_listings_price_type CHECK (
        (price_type IN ('fixed','negotiable') AND price IS NOT NULL)
     OR (price_type = 'free'    AND (price IS NULL OR price = 0))
     OR (price_type = 'contact' AND price IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_images (
  id             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  listing_id     BIGINT UNSIGNED   NOT NULL,
  file_name      VARCHAR(64)       NOT NULL,                 -- random name generated by the server
  mime_type      VARCHAR(32)       NOT NULL,
  width          SMALLINT UNSIGNED NOT NULL,
  height         SMALLINT UNSIGNED NOT NULL,
  size_bytes     INT UNSIGNED      NOT NULL,
  position       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  is_primary     TINYINT(1)        NOT NULL DEFAULT 0,
  primary_flag   TINYINT           GENERATED ALWAYS AS (IF(is_primary = 1, 1, NULL)) STORED,
  created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_listing_images_file (file_name),
  UNIQUE KEY uq_listing_images_primary (listing_id, primary_flag),   -- at most one primary image per listing
  KEY ix_listing_images_order (listing_id, position),
  CONSTRAINT fk_listing_images_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per listing per day: reliable, privacy-light view statistics.
CREATE TABLE listing_view_daily (
  listing_id  BIGINT UNSIGNED NOT NULL,
  viewed_on   DATE            NOT NULL,
  views       INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (listing_id, viewed_on),
  KEY ix_lvd_day (viewed_on),
  CONSTRAINT fk_lvd_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- favorites
CREATE TABLE favorites (
  user_id     BIGINT UNSIGNED NOT NULL,
  listing_id  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),
  KEY ix_favorites_listing (listing_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ messaging
CREATE TABLE conversations (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id       BIGINT UNSIGNED NOT NULL,
  buyer_id         BIGINT UNSIGNED NOT NULL,
  seller_id        BIGINT UNSIGNED NOT NULL,
  last_message_at  DATETIME        NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conversations_thread (listing_id, buyer_id),
  KEY ix_conversations_buyer (buyer_id, last_message_at),
  KEY ix_conversations_seller (seller_id, last_message_at),
  CONSTRAINT fk_conversations_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
  CONSTRAINT fk_conversations_buyer FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_conversations_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE
  -- buyer_id <> seller_id is enforced in ConversationService (MySQL forbids CHECK on columns with CASCADE FKs, error 3823)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  sender_id        BIGINT UNSIGNED NOT NULL,
  body             VARCHAR(2000)   NOT NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at          DATETIME        NULL,                     -- set when the OTHER participant reads it
  PRIMARY KEY (id),
  KEY ix_messages_thread (conversation_id, id),
  KEY ix_messages_unread (conversation_id, sender_id, read_at),
  KEY ix_messages_sender (sender_id),
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        VARCHAR(40)     NOT NULL,                      -- listing_approved, listing_rejected, new_message, ...
  data        JSON            NOT NULL,                      -- ids/params only; text is translated at display time
  read_at     DATETIME        NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notifications_user (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------- moderation & administration
CREATE TABLE listing_reports (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id   BIGINT UNSIGNED NOT NULL,
  reporter_id  BIGINT UNSIGNED NOT NULL,
  reason       ENUM('spam','prohibited','fraud','wrong_category','other') NOT NULL,
  details      VARCHAR(1000)   NULL,
  status       ENUM('open','dismissed','actioned') NOT NULL DEFAULT 'open',
  handled_by   BIGINT UNSIGNED NULL,
  handled_at   DATETIME        NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reports_once (listing_id, reporter_id),
  KEY ix_reports_status (status, created_at),
  KEY ix_reports_reporter (reporter_id),
  KEY ix_reports_handler (handled_by),
  CONSTRAINT fk_reports_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_activity_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id  BIGINT UNSIGNED NULL,
  action         VARCHAR(64)     NOT NULL,                   -- e.g. listing.approve, user.suspend
  entity_type    VARCHAR(32)     NOT NULL,
  entity_id      BIGINT UNSIGNED NULL,
  result         ENUM('success','denied','failed') NOT NULL,
  meta           JSON            NULL,                       -- never passwords, tokens or secrets
  ip_hash        CHAR(64)        NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_aal_admin (admin_user_id, created_at),
  KEY ix_aal_entity (entity_type, entity_id),
  KEY ix_aal_created (created_at),
  CONSTRAINT fk_aal_admin FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
  setting_key    VARCHAR(64)  NOT NULL,
  setting_value  TEXT         NOT NULL,
  is_public      TINYINT(1)   NOT NULL DEFAULT 0,            -- safe to expose to the front-end (e.g. site_name)
  updated_by     BIGINT UNSIGNED NULL,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY ix_site_settings_user (updated_by),
  CONSTRAINT fk_site_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
