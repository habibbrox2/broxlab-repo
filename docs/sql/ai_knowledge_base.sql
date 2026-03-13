-- AI Knowledge Base Table
-- This table stores knowledge base entries for the AI assistant

CREATE TABLE
IF NOT EXISTS ai_knowledge_base
(
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR
(255) NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    category VARCHAR
(100) DEFAULT NULL,
    source_type VARCHAR
(50) NOT NULL DEFAULT 'text',
    is_active TINYINT
(1) NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial knowledge base entries
INSERT INTO ai_knowledge_base
    (id, title, content, source_type, is_active, priority, created_at, updated_at)
VALUES
    (1, 'Company Overview', 'BroxBhai is a full-stack web application built with PHP for managing content, services, devices, notifications, and AI-driven features. It includes a rich admin panel, API endpoints, Telegram integration, automated content tools, and a full-featured backend. The platform supports user management, role-based access control, content management, and real-time notifications.', 'text', 1, 10, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (2, 'Admin Panel Features', 'The Admin Panel includes: User Management (create, edit, delete users, assign roles), Content Management (posts, pages, categories, tags), Media Library (image/video upload, management), Notification System (push, email, SMS), Service Applications (accept, process service requests), Device Control (IoT device management), AI System (OpenRouter integration, knowledge base), Reports & Analytics, and System Settings.', 'text', 1, 9, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (3, 'User Roles and Permissions', 'BroxBhai supports role-based access control with the following roles: Super Admin (full system access), Admin (content and user management), Editor (content creation and editing), Author (own content management), and Guest (read-only access). Permissions are granular and can be assigned to specific roles for fine-tuned access control.', 'text', 1, 8, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (4, 'API Endpoints Reference', 'Key API endpoints: /api/user-notifications - Get user notifications, /api/notification/mark-read - Mark notification as read, /api/admin/applications - Admin service applications, /api/admin/ai-knowledge - AI knowledge base management, /api/ai/chat - AI chat endpoint, /api/log-activity - Activity logging. All POST endpoints require CSRF token in header or form field.', 'text', 1, 7, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (5, 'AI System Capabilities', 'The AI System provides: AI Chat Interface (conversational AI assistant), Knowledge Base (custom knowledge slices for AI context), Multiple AI Providers (OpenRouter, OpenAI, Anthropic), Model Management (add, configure, test AI models), Content Enhancement (auto-generate summaries, improve writing), and Web Scraping Integration (AI-powered content collection).', 'text', 1, 6, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (6, 'Notification System', 'BroxBhai supports multiple notification channels: Push Notifications (via Firebase FCM), Email Notifications (SMTP configuration required), SMS Notifications (future integration), In-App Notifications (real-time), and Telegram Bot Notifications. Administrators can send bulk notifications to all users, specific roles, or individual users.', 'text', 1, 5, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (7, 'Content Management System', 'The CMS supports: Posts (blog articles with categories and tags), Pages (static pages), Media Library (images, videos, documents), Categories (hierarchical organization), Tags (flexible labeling), Comments (user feedback system), and SEO Tools (meta tags, sitemaps). Content can be scheduled for future publishing.', 'text', 1, 4, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (8, 'Security and Authentication', 'Security features include: CSRF Protection on all forms, XSS Prevention (input sanitization), SQL Injection Prevention (prepared statements), Two-Factor Authentication (TOTP), Account Lockout (after failed attempts), Password Requirements (configurable complexity), Session Management (timeout, remember me), and Audit Logging (all admin actions).', 'text', 1, 3, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (9, 'Service Application Process', 'Service applications workflow: 1. User fills application form with required documents, 2. System creates application with pending status, 3. Admin reviews application, 4. Admin approves/rejects with notes, 5. User receives notification of decision, 6. Application status updated in system. Supports: approval workflow, document upload, payment integration, and status tracking.', 'text', 1, 2, '2026-03-12 17:01:58', '2026-03-12 17:01:58'),
    (10, 'Deployment Guide', 'To deploy BroxBhai: 1. Clone repository, 2. Run composer install, 3. Run npm install, 4. Create database and import schema, 5. Configure .env or config files, 6. Set storage permissions, 7. Build assets with npm run build, 8. Point webroot to public_html/. For production use HTTPS, configure cache, and enable maintenance mode during updates.', 'text', 1, 1, '2026-03-12 17:01:58', '2026-03-12 17:01:58');
