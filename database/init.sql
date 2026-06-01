-- 1. TABLE DES UTILISATEURS (Gère Owners, Sitters et Admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Pour le hash bcrypt
    role ENUM('pet-owner', 'pet-sitter', 'admin') NOT NULL,
    bio TEXT NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. TABLE DES ANIMAUX (Liée aux Owners)
-- Obligatoire pour savoir qui on garde.
CREATE TABLE pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    species ENUM('dog', 'cat', 'bird', 'other') NOT NULL,
    breed VARCHAR(50) NULL,
    age INT NULL,
    description TEXT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABLE DES ANNONCES (Ads - Créées par les Owners)
CREATE TABLE ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    pet_id INT NOT NULL,
    service_type ENUM('sitting', 'walking') NOT NULL,
    location VARCHAR(150) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    price_offered DECIMAL(10,2) NOT NULL, -- DECIMAL pour l'argent, JAMAIS de FLOAT
    status ENUM('open', 'assigned', 'completed', 'cancelled') DEFAULT 'open',
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TABLE DES CANDIDATURES (Applications - Faites par les Sitters)
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    sitter_id INT NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (sitter_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (ad_id, sitter_id) -- Un sitter ne postule qu'une fois par annonce
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. TABLE DES ÉVALUATIONS (Reviews - Pour le système de notation)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    reviewer_id INT NOT NULL, -- Celui qui écrit (Owner ou Sitter)
    reviewee_id INT NOT NULL, -- Celui qui est noté
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5), -- Note de 1 à 5
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;