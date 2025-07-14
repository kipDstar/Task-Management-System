<?php
// Check if user is logged in
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Organize Your Life</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1><i class="fas fa-tasks"></i> TaskFlow</h1>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-view="dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a></li>
                    <li><a href="#" class="nav-link" data-view="all-tasks">
                        <i class="fas fa-list"></i> All Tasks
                    </a></li>
                    <li><a href="#" class="nav-link" data-view="today">
                        <i class="fas fa-calendar-day"></i> Today
                    </a></li>
                    <li><a href="#" class="nav-link" data-view="upcoming">
                        <i class="fas fa-calendar-alt"></i> Upcoming
                    </a></li>
                    <li><a href="#" class="nav-link" data-view="completed">
                        <i class="fas fa-check-circle"></i> Completed
                    </a></li>
                    <li class="admin-only" style="display: none;"><a href="#" class="nav-link" data-view="users">
                        <i class="fas fa-users"></i> Users
                    </a></li>
                </ul>
            </nav>

            <div class="sidebar-section">
                <h3>Projects</h3>
                <ul id="projectsList" class="projects-list">
                    <!-- Dynamic project list -->
                </ul>
                <button class="btn-add-project" id="addProjectBtn">
                    <i class="fas fa-plus"></i> Add Project
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 id="pageTitle">Dashboard</h2>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search tasks...">
                    </div>
                    <div class="user-menu">
                        <span id="userName">User</span>
                        <button class="btn-logout" id="logoutBtn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </div>
                    <button class="btn-primary" id="addTaskBtn">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
            </header>

            <!-- Dashboard View -->
            <div id="dashboard" class="view active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalTasks">0</h3>
                            <p>Total Tasks</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="completedTasks">0</h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="pendingTasks">0</h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon overdue">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="overdueTasks">0</h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-sections">
                    <div class="section">
                        <h3>Today's Tasks</h3>
                        <div id="todayTasks" class="task-list">
                            <!-- Today's tasks will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>Upcoming Deadlines</h3>
                        <div id="upcomingTasks" class="task-list">
                            <!-- Upcoming tasks will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Tasks View -->
            <div id="all-tasks" class="view">
                <div class="filters-bar">
                    <div class="filter-group">
                        <select id="statusFilter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                        
                        <select id="priorityFilter" class="filter-select">
                            <option value="">All Priority</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        
                        <select id="projectFilter" class="filter-select">
                            <option value="">All Projects</option>
                        </select>
                    </div>
                    
                    <div class="sort-group">
                        <select id="sortBy" class="filter-select">
                            <option value="created_desc">Newest First</option>
                            <option value="created_asc">Oldest First</option>
                            <option value="due_asc">Due Date (Soon)</option>
                            <option value="due_desc">Due Date (Late)</option>
                            <option value="priority_desc">Priority (High)</option>
                            <option value="priority_asc">Priority (Low)</option>
                        </select>
                    </div>
                </div>
                
                <div id="allTasksList" class="task-list">
                    <!-- All tasks will be loaded here -->
                </div>
            </div>

            <!-- Other Views -->
            <div id="today" class="view">
                <div id="todayTasksList" class="task-list">
                    <!-- Today's tasks will be loaded here -->
                </div>
            </div>

            <div id="upcoming" class="view">
                <div id="upcomingTasksList" class="task-list">
                    <!-- Upcoming tasks will be loaded here -->
                </div>
            </div>

            <div id="completed" class="view">
                <div id="completedTasksList" class="task-list">
                    <!-- Completed tasks will be loaded here -->
                </div>
            </div>

            <!-- Users View (Admin Only) -->
            <div id="users" class="view">
                <div class="view-header">
                    <h3>User Management</h3>
                    <button class="btn-primary" id="addUserBtn">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
                
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Task</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="taskForm" class="modal-body">
                <input type="hidden" id="taskId" name="taskId">
                
                <div class="form-group">
                    <label for="taskTitle">Task Title *</label>
                    <input type="text" id="taskTitle" name="taskTitle" required placeholder="Enter task title">
                </div>
                
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="taskDescription" rows="3" placeholder="Enter task description"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="taskPriority">Priority</label>
                        <select id="taskPriority" name="taskPriority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="taskProject">Project</label>
                        <select id="taskProject" name="taskProject">
                            <option value="">No Project</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row admin-only" style="display: none;">
                    <div class="form-group">
                        <label for="taskAssignedTo">Assign To</label>
                        <select id="taskAssignedTo" name="taskAssignedTo">
                            <option value="">Select User</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="taskDueDate">Due Date</label>
                        <input type="date" id="taskDueDate" name="taskDueDate">
                    </div>
                    
                    <div class="form-group">
                        <label for="taskDueTime">Due Time</label>
                        <input type="time" id="taskDueTime" name="taskDueTime">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="taskTags">Tags (comma separated)</label>
                    <input type="text" id="taskTags" name="taskTags" placeholder="work, urgent, meeting">
                </div>
            </form>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelTask">Cancel</button>
                <button type="submit" form="taskForm" class="btn-primary" id="saveTask">Save Task</button>
            </div>
        </div>
    </div>

    <!-- Project Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Project</h3>
                <button class="modal-close" id="closeProjectModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="projectForm" class="modal-body">
                <div class="form-group">
                    <label for="projectName">Project Name *</label>
                    <input type="text" id="projectName" name="projectName" required placeholder="Enter project name">
                </div>
                
                <div class="form-group">
                    <label for="projectColor">Color</label>
                    <div class="color-picker">
                        <input type="color" id="projectColor" name="projectColor" value="#667eea">
                    </div>
                </div>
            </form>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelProject">Cancel</button>
                <button type="submit" form="projectForm" class="btn-primary">Save Project</button>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="userModalTitle">Add New User</h3>
                <button class="modal-close" id="closeUserModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="userForm" class="modal-body">
                <input type="hidden" id="userId" name="userId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="userFirstName">First Name</label>
                        <input type="text" id="userFirstName" name="userFirstName" placeholder="Enter first name">
                    </div>
                    
                    <div class="form-group">
                        <label for="userLastName">Last Name</label>
                        <input type="text" id="userLastName" name="userLastName" placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="userUsername">Username *</label>
                    <input type="text" id="userUsername" name="userUsername" required placeholder="Enter username">
                </div>
                
                <div class="form-group">
                    <label for="userEmail">Email *</label>
                    <input type="email" id="userEmail" name="userEmail" required placeholder="Enter email">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="userPassword">Password</label>
                        <input type="password" id="userPassword" name="userPassword" placeholder="Enter password">
                        <small>Leave blank to keep current password when editing</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="userRole">Role</label>
                        <select id="userRole" name="userRole">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="userStatus">Status</label>
                    <select id="userStatus" name="userStatus">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </form>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelUser">Cancel</button>
                <button type="submit" form="userForm" class="btn-primary" id="saveUser">Save User</button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading" class="loading" style="display: none;">
        <div class="spinner"></div>
        <p>Loading...</p>
    </div>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="assets/js/script.js"></script>
</body>
</html>