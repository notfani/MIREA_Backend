-- 01-init.sql
-- Создаём отдельную БД и пользователя (если ещё нет)
CREATE DATABASE IF NOT EXISTS marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'marketuser'@'%' IDENTIFIED BY 'marketpass123';
GRANT ALL PRIVILEGES ON marketplace.* TO 'marketuser'@'%';
FLUSH PRIVILEGES;

USE marketplace;

-- 1. Справочник ролей
CREATE TABLE roles (
                       id   TINYINT AUTO_INCREMENT PRIMARY KEY,
                       name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 2. Пользователи
CREATE TABLE users (
                       id         INT AUTO_INCREMENT PRIMARY KEY,
                       email      VARCHAR(120) NOT NULL UNIQUE,
                       pass_hash  CHAR(60)     NOT NULL,
                       full_name  VARCHAR(120) NOT NULL,
                       role_id    TINYINT      NOT NULL DEFAULT 2,
                       created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       CONSTRAINT fk_users_role
                           FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- 3. Категории товаров
CREATE TABLE categories (
                            id          SMALLINT AUTO_INCREMENT PRIMARY KEY,
                            title       VARCHAR(100) NOT NULL,
                            slug        VARCHAR(100) NOT NULL UNIQUE,
                            description TEXT
) ENGINE=InnoDB;

-- 4. Товары
CREATE TABLE products (
                          id          INT AUTO_INCREMENT PRIMARY KEY,
                          category_id SMALLINT     NOT NULL,
                          vendor_code VARCHAR(50)  NOT NULL UNIQUE,
                          title       VARCHAR(150) NOT NULL,
                          price       DECIMAL(12,2) NOT NULL CHECK (price >= 0),
                          in_stock    INT          NOT NULL DEFAULT 0 CHECK (in_stock >= 0),
                          created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                          updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          INDEX idx_cat_price (category_id, price),
                          CONSTRAINT fk_prod_cat
                              FOREIGN KEY (category_id) REFERENCES categories(id)
                                  ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 5. Статусы заказа
CREATE TABLE order_statuses (
                                id   TINYINT AUTO_INCREMENT PRIMARY KEY,
                                name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 6. Заказы
CREATE TABLE orders (
                        id          BIGINT AUTO_INCREMENT PRIMARY KEY,
                        user_id     INT       NOT NULL,
                        status_id   TINYINT   NOT NULL DEFAULT 1,
                        total       DECIMAL(14,2) NOT NULL DEFAULT 0,
                        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_user_stat (user_id, status_id),
                        CONSTRAINT fk_ord_user
                            FOREIGN KEY (user_id) REFERENCES users(id),
                        CONSTRAINT fk_ord_status
                            FOREIGN KEY (status_id) REFERENCES order_statuses(id)
) ENGINE=InnoDB;

-- 7. Позиции заказа
CREATE TABLE order_items (
                             order_id   BIGINT NOT NULL,
                             product_id INT    NOT NULL,
                             qty        INT    NOT NULL CHECK (qty > 0),
                             price      DECIMAL(12,2) NOT NULL,
                             PRIMARY KEY (order_id, product_id),
                             CONSTRAINT fk_oi_order  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
                             CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 8. Корзина (временная таблица, можно чистить кроном)
CREATE TABLE cart (
                      user_id    INT NOT NULL,
                      product_id INT NOT NULL,
                      qty        INT NOT NULL DEFAULT 1 CHECK (qty > 0),
                      added_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (user_id, product_id),
                      CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
                      CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===== Справочники =====
INSERT INTO roles (id, name) VALUES
                                 (1, 'admin'),
                                 (2, 'customer');

INSERT INTO order_statuses (id, name) VALUES
                                          (1, 'new'),
                                          (2, 'paid'),
                                          (3, 'shipped'),
                                          (4, 'delivered'),
                                          (5, 'cancelled');

INSERT INTO categories (title, slug, description) VALUES
                                                      ('Смартфоны', 'smartphones', 'Мобильные телефоны и аксессуары'),
                                                      ('Наушники',  'headphones',  'Аудио-гарнитуры'),
                                                      ('Планшеты',  'tablets',     'Планшетные компьютеры');

-- ===== Тестовые пользователи (пароль = 123456) =====
INSERT INTO users (email, pass_hash, full_name, role_id) VALUES
                                                             ('admin@market.local',
                                                              '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                                                              'Администратор', 1),
                                                             ('user@market.local',
                                                              '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                                                              'Пётр Покупатель', 2);

-- ===== Тестовые товары =====
INSERT INTO products (category_id, vendor_code, title, price, in_stock) VALUES
                                                                            (1, 'PH-13-128-BLK',
                                                                             'iPhone 13 128 GB Black',   69990.00,
                                                                             10),
                                                                            (1, 'SM-A546-GRN',
                                                                             'Samsung A54 6/128 Green',  32990.00,
                                                                             15),
                                                                            (2, 'AP-AIRP-3-WHT',
                                                                             'Apple AirPods 3 White',    17990.00,
                                                                             25),
                                                                            (3, 'TB-IPAD-10-64',
                                                                             'iPad 10 64 GB Silver',     44990.00,
                                                                             8);

-- ===== Хранимые процедуры =====
DELIMITER //
CREATE PROCEDURE sp_user_orders(IN p_user_id INT)
BEGIN
SELECT o.id,
       os.name AS status,
       o.total,
       o.created_at
FROM orders o
         JOIN order_statuses os ON os.id = o.status_id
WHERE o.user_id = p_user_id
ORDER BY o.created_at DESC;
END //

CREATE PROCEDURE sp_add_to_cart(IN p_user_id INT, IN p_product_id INT, IN p_qty INT)
BEGIN
INSERT INTO cart (user_id, product_id, qty)
VALUES (p_user_id, p_product_id, p_qty)
    ON DUPLICATE KEY UPDATE qty = qty + p_qty;
END //
DELIMITER ;

-- ===== Итоговые права (на всякий) =====
GRANT SELECT, INSERT, UPDATE, DELETE ON marketplace.* TO 'marketuser'@'%';
FLUSH PRIVILEGES;