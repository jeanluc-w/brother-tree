-- Brother Tree WordPress Plugin Schema

-- Table: wp_fraternity_members
CREATE TABLE wp_fraternity_members (
    member_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    big_brother_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    pledge_year INT NOT NULL,
    pledge_semester ENUM('Spring', 'Summer', 'Fall') NOT NULL,
    grad_year INT,
    grad_semester ENUM('Spring', 'Summer', 'Fall'),
    photo_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_big_brother (big_brother_id),
    FOREIGN KEY (big_brother_id) REFERENCES wp_fraternity_members(member_id) ON DELETE SET NULL
);

-- Table: wp_fraternity_lines
CREATE TABLE wp_fraternity_lines (
    line_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_name VARCHAR(255) NOT NULL,
    founder_member_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (founder_member_id) REFERENCES wp_fraternity_members(member_id) ON DELETE CASCADE
);

-- Table: wp_fraternity_member_line (many-to-many member to line mapping)
CREATE TABLE wp_fraternity_member_line (
    member_id BIGINT UNSIGNED NOT NULL,
    line_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (member_id, line_id),
    FOREIGN KEY (member_id) REFERENCES wp_fraternity_members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (line_id) REFERENCES wp_fraternity_lines(line_id) ON DELETE CASCADE
);

-- Table: wp_fraternity_member_majors
CREATE TABLE wp_fraternity_member_majors (
    member_id BIGINT UNSIGNED NOT NULL,
    major VARCHAR(255) NOT NULL,
    PRIMARY KEY (member_id, major),
    FOREIGN KEY (member_id) REFERENCES wp_fraternity_members(member_id) ON DELETE CASCADE
);

-- Table: wp_fraternity_member_minors
CREATE TABLE wp_fraternity_member_minors (
    member_id BIGINT UNSIGNED NOT NULL,
    minor VARCHAR(255) NOT NULL,
    PRIMARY KEY (member_id, minor),
    FOREIGN KEY (member_id) REFERENCES wp_fraternity_members(member_id) ON DELETE CASCADE
);

-- Table: wp_fraternity_member_degrees
CREATE TABLE wp_fraternity_member_degrees (
    member_id BIGINT UNSIGNED NOT NULL,
    degree VARCHAR(255) NOT NULL,
    PRIMARY KEY (member_id, degree),
    FOREIGN KEY (member_id) REFERENCES wp_fraternity_members(member_id) ON DELETE CASCADE
);
