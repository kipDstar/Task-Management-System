# Tasky - Task Management System

A modern, role-based task management application built with PHP, JavaScript, and SQLite.

---

## Table of Contents

- [Features](#features)
- [How It Works](#how-it-works)
- [Setup & Installation](#setup--installation)
- [Default Users](#default-users)
- [User Roles & Permissions](#user-roles--permissions)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Security Features](#security-features)
- [Email Notifications](#email-notifications)
- [File Structure](#file-structure)
- [Troubleshooting](#troubleshooting)
- [Development & Customization](#development--customization)
- [License](#license)

---

## Features

- **Authentication & User Management**
  - Secure login/logout with session tokens
  - Role-based access: Admin and User
  - Admins can manage users (add, edit, delete, activate/deactivate)
- **Task Management**
  - Create, edit, delete tasks
  - Assign tasks (admins can assign to anyone, users to themselves)
  - Task status: Pending, In Progress, Completed
  - Priority: High, Medium, Low
  - Due dates and times
  - Tagging and project grouping
- **Project Management**
  - Create, edit, delete projects
  - Color-coded projects
  - Filter tasks by project
- **Dashboard & Analytics**
  - See total, completed, pending, and overdue tasks
  - View today's and upcoming tasks
  - Search and filter tasks
- **Email Notifications**
  - (Optional) Email sent when a task is assigned

---

## How It Works

### User Experience

- **Login:** Users log in at `/frontend/login.php` using their credentials.
- **Dashboard:** After login, users see a dashboard with stats and their tasks.
- **Task Management:** Users can create tasks (assigned to themselves). Admins can assign tasks to any user.
- **Project Management:** Tasks can be grouped into projects for better organization.
- **User Management:** Admins can manage users via the Users tab.
- **Role-Based UI:** Admin-only features are hidden from regular users.

### Technical Flow

- **Frontend:** All UI is in `frontend/index.php` and handled by `frontend/assets/js/script.js`.
- **Backend:** All data is served via PHP API endpoints in `/backend/api/`.
- **Database:** SQLite file at `backend/data/tasks.db` (auto-created on first run).
- **Authentication:** Uses session tokens stored in localStorage and sent via HTTP headers.

---

## Setup & Installation

### Prerequisites

- PHP 7.4 or higher (with SQLite extension enabled)
- Any web server (Apache, Nginx, or PHP built-in server)
- Git (optional, for cloning)

### Quick Start

1. **Clone or Download the Project**
   ```bash
   git clone <repository-url>
   cd Task-Manager-Project
   ```

2. **Start the PHP Server**
   ```bash
   # Serve from the root directory to access both frontend and backend
   php -S localhost:8000
   ```
   Or configure your web server to point to this directory.

3. **Set Permissions**
   - Make sure the `backend/data/` directory is writable by the web server:
     ```bash
     chmod 775 backend/data
     ```

4. **Access the App**
   - Open your browser and go to: [http://localhost:8000/frontend/login.php](http://localhost:8000/frontend/login.php)
   - The database and sample data will be created automatically on first run.

5. **Test Authentication**
   - Visit [http://localhost:8000/backend/test_auth.php](http://localhost:8000/backend/test_auth.php) to verify the authentication system.

---

## Default Users

| Username | Password  | Role  | Description           |
|----------|-----------|-------|-----------------------|
| admin    | admin123  | Admin | Full system access    |
| john     | user123   | User  | Regular user access   |
| jane     | user123   | User  | Regular user access   |

---

## User Roles & Permissions

### Admin
- Manage users (add, edit, delete, activate/deactivate)
- Assign tasks to any user
- View and manage all tasks and projects
- Delete any task or project

### User
- View and update only their own tasks
- Create tasks (assigned to themselves)
- Cannot manage users or assign tasks to others

---

## API Endpoints

- **Authentication**
  - `POST /backend/api/auth_endpoints.php?action=login` (login)
  - `POST /backend/api/auth_endpoints.php?action=logout` (logout)
  - `GET /backend/api/auth_endpoints.php?action=me` (current user info)
- **User Management (Admin only)**
  - `GET /backend/api/auth_endpoints.php?action=users`
  - `POST /backend/api/auth_endpoints.php?action=users`
  - `PUT /backend/api/auth_endpoints.php?action=users/update`
  - `DELETE /backend/api/auth_endpoints.php?action=users/delete`
- **Tasks**
  - `GET /backend/api/tasks.php`
  - `POST /backend/api/tasks.php`
  - `PUT /backend/api/tasks.php`
  - `DELETE /backend/api/tasks.php?id={id}`
- **Projects**
  - `GET /backend/api/projects.php`
  - `POST /backend/api/projects.php`
  - `PUT /backend/api/projects.php`
  - `DELETE /backend/api/projects.php?id={id}`

---

## Database Schema

- **users:** id, username, email, password_hash, role, first_name, last_name, is_active, created_at, updated_at
- **sessions:** id, user_id, session_token, expires_at, created_at
- **tasks:** id, title, description, status, priority, due_date, due_time, project_id, assigned_to, created_by, tags, created_at, updated_at
- **projects:** id, name, color, created_by, created_at

---

## Security Features

- Passwords hashed with bcrypt
- Secure session tokens
- All queries use prepared statements (SQL injection safe)
- XSS protection via input/output sanitization
- Role-based access enforced at API level

---

## Email Notifications

- Email notifications are implemented but **disabled by default**.
- To enable:
  1. Configure your server's mail settings.
  2. Uncomment the `mail()` function in `backend/api/tasks.php`.
  3. Update the sender email address as needed.

---

## File Structure

```
Task-Management-System/
├── backend/
│   ├── api/
│   │   ├── auth.php
│   │   ├── auth_endpoints.php
│   │   ├── database.php
│   │   ├── projects.php
│   │   ├── tasks.php
│   │   ├── realtime.php
│   │   ├── attachments.php
│   │   └── email_service.php
│   ├── config/
│   │   └── email_config.php
│   ├── data/
│   │   └── tasks.db
│   ├── uploads/
│   ├── config.php
│   ├── database_dump.sql
│   ├── test_auth.php
│   └── test.php
├── frontend/
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/script.js
│   ├── index.php
│   ├── login.php
│   └── .htaccess
├── README.md
├── PROJECT_SUMMARY.md
└── LICENSE
```

---

## Troubleshooting

- **Database errors:** Ensure `backend/data/` is writable and PHP SQLite extension is enabled.
- **Authentication issues:** Use `backend/test_auth.php` to verify, and check browser console for JS errors.
- **API not found:** Ensure `frontend/.htaccess` is present and mod_rewrite is enabled (if using Apache).
- **Permission errors:** Check your user role and login status.

---

## Development & Customization

- All business logic is in `/backend/api/` (PHP).
- Frontend logic is in `frontend/assets/js/script.js`.
- UI is in `frontend/index.php` and styled with `frontend/assets/css/style.css`.
- You can add new features by extending the PHP API and updating the JS frontend.

## Current Features

### ✅ Implemented Features
- **Real Email Integration:** SMTP email service with HTML templates
- **Real-time Updates:** Server-Sent Events for live task updates
- **File Attachments:** Upload and manage files with tasks (max 10MB)
- **Advanced Email Templates:** Task assignments, reminders, daily digests
- **Live Notifications:** Real-time task and user update notifications

### Planned Features
- **Advanced Analytics:** Productivity metrics, time tracking, performance dashboards
- **Smart Features:** AI-powered suggestions, automatic prioritization
- **Mobile PWA:** Progressive web app with offline support
- **Calendar Integration:** Google Calendar sync, meeting scheduling
- **Team Collaboration:** Workspaces, shared templates, team insights
- **Third-party Integrations:** Slack, GitHub, Google Workspace
- **Automation:** Workflow rules, auto-assignment, escalation

### Technical Roadmap
- **Backend:** API rate limiting, caching, database optimization
- **Frontend:** Component-based architecture, state management
- **Security:** Two-factor authentication, audit logging
- **Performance:** CDN integration, asset optimization

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
