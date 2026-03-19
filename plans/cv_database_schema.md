# CV Builder Database Schema

## Tables

### 1. users (existing)
Assuming the users table already exists with at least:
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- email (VARCHAR)
- password (VARCHAR)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

### 2. cvs
Stores metadata for each CV.

```sql
CREATE TABLE cvs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'My CV',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. cv_sections
Defines the sections within a CV (e.g., summary, experience, education). 
This allows for dynamic sections per CV.

```sql
CREATE TABLE cv_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cv_id INT NOT NULL,
    section_type VARCHAR(50) NOT NULL COMMENT 'summary, experience, education, skills, projects, certifications',
    title VARCHAR(255) NOT NULL COMMENT 'Custom title for the section (e.g., \"Work Experience\")',
    `order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cv_id) REFERENCES cvs(id) ON DELETE CASCADE,
    INDEX idx_cv_id (cv_id),
    INDEX idx_order (cv_id, `order`),
    INDEX idx_section_type (section_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4. cv_items
Stores the actual content of each section in a JSON format for flexibility.
Each item belongs to a section.

```sql
CREATE TABLE cv_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    item_type VARCHAR(50) NOT NULL COMMENT 'For experience: job, for education: degree, etc. Can be generic.',
    content_json JSON NOT NULL COMMENT 'Structured data for the item',
    `order` INT NOT NULL DEFAULT 0 COMMENT 'Display order within the section',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES cv_sections(id) ON DELETE CASCADE,
    INDEX idx_section_id (section_id),
    INDEX idx_order (section_id, `order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5. cv_shares
For shareable CVs via public token.

```sql
CREATE TABLE cv_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cv_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE COMMENT 'Public token for sharing',
    expires_at TIMESTAMP NULL COMMENT 'Optional expiration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cv_id) REFERENCES cvs(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_cv_id (cv_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Notes

1. **JSON Support**: MySQL 5.7+ supports JSON data type. We use `content_json` to store flexible item data.
   Example content for an experience item:
   ```json
   {
     "company": "Company Name",
     "position": "Software Engineer",
     "location": "City, Country",
     "start_date": "2020-01",
     "end_date": "2023-01",
     "description": ["Built web applications", "Led team of 5 developers"]
   }
   ```

2. **Extensibility**: 
   - New section types can be added by inserting into `cv_sections` with a new `section_type`.
   - New item types can be accommodated by the `item_type` field and the JSON structure.

3. **Indexes**:
   - Foreign key indexes are automatically created in InnoDB.
   - Additional indexes on `user_id`, `is_active`, `token`, and ordering columns for performance.

4. **Soft Deletes**: Not implemented; we use `is_active` on CVs and `is_visible` on sections. 
   For items, we could add an `is_visible` column if needed, but for simplicity we rely on deletion.

5. **Timestamps**: All tables have `created_at` and `updated_at` for audit trails.

## Migration Script (Example)

If needed, the following SQL can be used to create the tables (assuming users table exists):

```sql
-- Run these statements in sequence

CREATE TABLE cvs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'My CV',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cv_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cv_id INT NOT NULL,
    section_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    `order` INT NOT NULL DEFAULT 0,
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cv_id) REFERENCES cvs(id) ON DELETE CASCADE,
    INDEX idx_cv_id (cv_id),
    INDEX idx_order (cv_id, `order`),
    INDEX idx_section_type (section_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cv_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    content_json JSON NOT NULL,
    `order` INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES cv_sections(id) ON DELETE CASCADE,
    INDEX idx_section_id (section_id),
    INDEX idx_order (section_id, `order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cv_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cv_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cv_id) REFERENCES cvs(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_cv_id (cv_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```