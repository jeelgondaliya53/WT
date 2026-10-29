CREATE DATABASE IF NOT EXISTS studenthub
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'General',
    status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    poster VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Optional sample records:
INSERT INTO events (title, description, event_date, category, status)
VALUES
('Tech Fest', 'A student technology and innovation event.', '2027-01-15', 'Technology', 'Open'),
('Cultural Day', 'A celebration of student performances and culture.', '2027-02-10', 'Cultural', 'Open');
