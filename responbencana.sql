-- ============================================================
--  ResponBencana — Skema Database MySQL
--  Kelompok 4 · UMY · 2026
--  Jalankan file ini sekali untuk inisialisasi database.
-- ============================================================

CREATE DATABASE IF NOT EXISTS pdwp8946_Bencana
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pdwp8946_Bencana;

-- ------------------------------------------------------------
-- Tabel: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)    NOT NULL,
    email         VARCHAR(150)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: campaigns
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS campaigns (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    title          VARCHAR(255)    NOT NULL,
    description    TEXT,
    target_amount  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    deadline       DATE,
    is_active      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: donations
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS donations (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    campaign_id   INT UNSIGNED    NOT NULL DEFAULT 1,
    user_id       INT UNSIGNED    NOT NULL,
    amount        BIGINT UNSIGNED NOT NULL,
    comment       TEXT,
    bukti_file    VARCHAR(255),
    status        ENUM('Pending','Approved','Rejected')
                                  NOT NULL DEFAULT 'Approved',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_campaign (campaign_id),
    INDEX idx_user     (user_id),
    INDEX idx_status   (status),
    CONSTRAINT fk_donation_campaign
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_donation_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Data awal: admin
-- Password: admin123
-- ------------------------------------------------------------
INSERT INTO users (id, name, email, password_hash, role) VALUES
    (1, 'Administrator', 'admin@responbencana.id',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
     'admin');

-- ------------------------------------------------------------
-- Data awal: user donatur contoh
-- ------------------------------------------------------------
INSERT INTO users (id, name, email, password_hash, role) VALUES
    (2, 'Hamba Allah',  'hamba@example.com',  '$2y$10$dummyhashforseeding000000000000000000000000000000000000', 'user'),
    (3, 'Galang Dana',  'galang@example.com', '$2y$10$dummyhashforseeding000000000000000000000000000000000001', 'user'),
    (4, 'Zahwa Rezi',   'zahwa@example.com',  '$2y$10$dummyhashforseeding000000000000000000000000000000000002', 'user');

-- ------------------------------------------------------------
-- Data awal: kampanye
-- ------------------------------------------------------------
INSERT INTO campaigns (id, title, description, target_amount, deadline) VALUES
(1,
 'Dana Darurat Tanggap Bencana Nasional',
 'Galang dana darurat untuk korban bencana alam di seluruh Indonesia. Setiap donasi langsung disalurkan untuk kebutuhan logistik, medis, dan pemulihan.',
 250000000,
 DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
(2,
 'Bantuan Korban Gempa Sulawesi',
 'Penggalangan dana khusus untuk membantu korban gempa bumi di Sulawesi. Dana digunakan untuk pengadaan tenda darurat, makanan, dan obat-obatan.',
 150000000,
 DATE_ADD(CURDATE(), INTERVAL 45 DAY)),
(3,
 'Pemulihan Pasca Banjir Kalimantan',
 'Membantu masyarakat terdampak banjir besar di Kalimantan untuk memulihkan hunian dan mata pencaharian mereka.',
 100000000,
 DATE_ADD(CURDATE(), INTERVAL 60 DAY));

-- ------------------------------------------------------------
-- Data awal: donasi contoh
-- ------------------------------------------------------------
INSERT INTO donations (campaign_id, user_id, amount, comment, status, created_at) VALUES
    (1, 2, 1000000, 'Semoga berkah.',           'Approved', NOW() - INTERVAL 10 MINUTE),
    (1, 3,  500000, 'Bismillah, titip amanah.', 'Approved', NOW() - INTERVAL 2  HOUR),
    (1, 4,  250000, 'Semoga lancar.',            'Approved', NOW() - INTERVAL 5  HOUR),
    (2, 2,  300000, 'Untuk saudara di Sulawesi.','Approved', NOW() - INTERVAL 1  DAY),
    (3, 3,  200000, 'Semoga cepat pulih.',       'Approved', NOW() - INTERVAL 2  DAY);