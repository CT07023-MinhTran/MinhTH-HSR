-- Tạo database
CREATE DATABASE IF NOT EXISTS honkai_star_rail CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE honkai_star_rail;

-- Bảng admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Bảng Path (Vận Mệnh)
CREATE TABLE IF NOT EXISTS paths (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(255)
);

-- Bảng Element (Hệ)
CREATE TABLE IF NOT EXISTS elements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(255)
);

-- Bảng Characters (Nhân vật)
CREATE TABLE IF NOT EXISTS characters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    avatar VARCHAR(255),
    path_id INT,
    element_id INT,
    hp INT,
    atk INT,
    def INT,
    spd INT,
    rarity ENUM('4', '5') NOT NULL,
    description TEXT,
    FOREIGN KEY (path_id) REFERENCES paths(id),
    FOREIGN KEY (element_id) REFERENCES elements(id)
);

-- Bảng Lightcones (Nón ánh sáng)
CREATE TABLE IF NOT EXISTS lightcones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    path_id INT,
    hp INT,
    atk INT,
    def INT,
    rarity ENUM('4', '5') NOT NULL,
    effect TEXT,
    description TEXT,
    icon VARCHAR(255),
    FOREIGN KEY (path_id) REFERENCES paths(id)
);

-- Bảng Relics (Di vật)
CREATE TABLE IF NOT EXISTS relics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('Relic', 'Planetary Ornament Set') NOT NULL,
    icon VARCHAR(255),
    set2_effect TEXT,
    set4_effect TEXT
);

-- Bảng Materials (Nguyên liệu)
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    rarity ENUM('1','2','3','4','5') NULL,
    type VARCHAR(100) NULL,
    icon VARCHAR(255),
    description TEXT NULL,
    content TEXT,
    obtain TEXT NULL
);

-- Bảng Build (Đề xuất build cho nhân vật)
CREATE TABLE IF NOT EXISTS builds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT,
    lightcone1_id INT,
    lightcone2_id INT,
    relic1_id INT,
    relic2_id INT,
    ornament1_id INT,
    ornament2_id INT,
    stat_goal TEXT,
    FOREIGN KEY (character_id) REFERENCES characters(id),
    FOREIGN KEY (lightcone1_id) REFERENCES lightcones(id),
    FOREIGN KEY (lightcone2_id) REFERENCES lightcones(id),
    FOREIGN KEY (relic1_id) REFERENCES relics(id),
    FOREIGN KEY (relic2_id) REFERENCES relics(id),
    FOREIGN KEY (ornament1_id) REFERENCES relics(id),
    FOREIGN KEY (ornament2_id) REFERENCES relics(id)
);

-- Bảng Team (Đội hình)
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    character1_id INT,
    character2_id INT,
    character3_id INT,
    character4_id INT,
    role1 ENUM('DPS','Sub-DPS','Amplifier','Sustain'),
    role2 ENUM('DPS','Sub-DPS','Amplifier','Sustain'),
    role3 ENUM('DPS','Sub-DPS','Amplifier','Sustain'),
    role4 ENUM('DPS','Sub-DPS','Amplifier','Sustain'),
    FOREIGN KEY (character1_id) REFERENCES characters(id),
    FOREIGN KEY (character2_id) REFERENCES characters(id),
    FOREIGN KEY (character3_id) REFERENCES characters(id),
    FOREIGN KEY (character4_id) REFERENCES characters(id)
);