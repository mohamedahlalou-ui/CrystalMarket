-- ============================================================================
-- CrystalMarket — essential seed data (configuration only).
-- NO users, NO listings, NO passwords, NO demo content. The first administrator
-- is created by install.php with a password chosen by the installer.
-- The installer overrides site_name / default_locale / default_currency /
-- timezone / contact_email with the values entered in the wizard.
-- ============================================================================

SET NAMES utf8mb4;

INSERT INTO roles (slug, name) VALUES
  ('member',    'Member'),
  ('moderator', 'Moderator'),
  ('admin',     'Administrator');

INSERT INTO permissions (slug, description) VALUES
  ('admin.access',        'Open the administration area'),
  ('listings.moderate',   'Approve, reject, suspend and archive any listing'),
  ('listings.delete',     'Delete any listing'),
  ('reports.manage',      'Handle listing reports'),
  ('users.view',          'View user accounts'),
  ('users.manage',        'Restrict, suspend and reactivate accounts'),
  ('users.roles',         'Change the role of another user'),
  ('categories.manage',   'Create, edit, reorder and delete categories'),
  ('settings.manage',     'Edit site settings'),
  ('logs.view',           'View the administration activity log');

-- moderator: moderation only
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.slug IN ('admin.access','listings.moderate','reports.manage','users.view')
WHERE r.slug = 'moderator';

-- admin: everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'admin';

-- Categories (the eight tiles of the reference design)
INSERT INTO categories (slug, icon, has_condition, sort_order) VALUES
  ('cars',            'car',      1, 10),
  ('real-estate',     'home',     0, 20),
  ('electronics',     'laptop',   1, 30),
  ('home-garden',     'sofa',     1, 40),
  ('fashion',         'bag',      1, 50),
  ('sports-leisure',  'bike',     1, 60),
  ('services',        'wrench',   0, 70),
  ('other',           'grid',     1, 80);

INSERT INTO category_translations (category_id, locale, name)
SELECT c.id, t.locale, t.name FROM categories c JOIN (
  SELECT 'cars' slug, 'fr' locale, 'Voitures' name UNION ALL SELECT 'cars','nl','Auto''s' UNION ALL SELECT 'cars','en','Cars' UNION ALL SELECT 'cars','es','Coches'
  UNION ALL SELECT 'real-estate','fr','Immobilier' UNION ALL SELECT 'real-estate','nl','Vastgoed' UNION ALL SELECT 'real-estate','en','Real estate' UNION ALL SELECT 'real-estate','es','Inmobiliaria'
  UNION ALL SELECT 'electronics','fr','Électronique' UNION ALL SELECT 'electronics','nl','Elektronica' UNION ALL SELECT 'electronics','en','Electronics' UNION ALL SELECT 'electronics','es','Electrónica'
  UNION ALL SELECT 'home-garden','fr','Maison & Jardin' UNION ALL SELECT 'home-garden','nl','Huis & Tuin' UNION ALL SELECT 'home-garden','en','Home & Garden' UNION ALL SELECT 'home-garden','es','Hogar y Jardín'
  UNION ALL SELECT 'fashion','fr','Mode' UNION ALL SELECT 'fashion','nl','Mode' UNION ALL SELECT 'fashion','en','Fashion' UNION ALL SELECT 'fashion','es','Moda'
  UNION ALL SELECT 'sports-leisure','fr','Sports & Loisirs' UNION ALL SELECT 'sports-leisure','nl','Sport & Vrije tijd' UNION ALL SELECT 'sports-leisure','en','Sports & Leisure' UNION ALL SELECT 'sports-leisure','es','Deporte y Ocio'
  UNION ALL SELECT 'services','fr','Services' UNION ALL SELECT 'services','nl','Diensten' UNION ALL SELECT 'services','en','Services' UNION ALL SELECT 'services','es','Servicios'
  UNION ALL SELECT 'other','fr','Autres' UNION ALL SELECT 'other','nl','Overig' UNION ALL SELECT 'other','en','Other' UNION ALL SELECT 'other','es','Otros'
) t ON t.slug = c.slug;

INSERT INTO site_settings (setting_key, setting_value, is_public) VALUES
  ('site_name',                 'CrystalMarket',                 1),
  ('site_tagline',              '',                              1),
  ('logo_file',                 '',                              1),
  ('default_locale',            'fr',                            1),
  ('enabled_locales',           '["fr","nl","en","es"]',         1),
  ('default_currency',          'EUR',                           1),
  ('timezone',                  'UTC',                           0),
  ('default_theme',             'light',                         1),
  ('enabled_themes',            '["light","dark","green","rose"]', 1),
  ('contact_email',             '',                              1),
  ('registration_open',         '1',                             0),
  ('email_verification_required','0',                            0),
  ('mail_driver',               'none',                          0),
  ('require_listing_review',    '1',                             0),
  ('featured_listings_enabled', '1',                             1),
  ('listing_expiry_days',       '0',                             0),
  ('max_images_per_listing',    '8',                             1),
  ('max_image_size_kb',         '5120',                          1),
  ('max_image_dimension_px',    '6000',                          0),
  ('listings_per_page',         '24',                            1),
  ('message_rate_per_minute',   '10',                            0),
  ('seo_default_description',   '',                              1),
  ('privacy_policy_url',        '',                              1),
  ('maintenance_mode',          '0',                             0),
  ('maintenance_message',       '',                              0),
  ('installed_at',              '',                              0);
