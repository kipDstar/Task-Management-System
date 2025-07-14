class TaskManager {
    constructor() {
        this.currentView = 'dashboard';
        this.tasks = [];
        this.projects = [];
        this.users = [];
        this.filteredTasks = [];
        this.searchTerm = '';
        this.filters = {
            status: '',
            priority: '',
            project: ''
        };
        this.sortBy = 'created_desc';
        this.currentUser = null;
        this.sessionToken = null;
        this.eventSource = null;
        this.realtimeEnabled = true;
        
        this.init();
    }

    init() {
        this.checkAuth();
        this.bindEvents();
        this.loadProjects();
        this.loadTasks();
        this.loadUsers();
        this.updateUI();
        this.initRealtimeUpdates();
    }

    checkAuth() {
        this.sessionToken = localStorage.getItem('sessionToken');
        const userData = localStorage.getItem('user');
        
        if (!this.sessionToken || !userData) {
            window.location.href = 'login.php';
            return;
        }
        
        try {
            this.currentUser = JSON.parse(userData);
            this.updateUserInterface();
        } catch (error) {
            console.error('Error parsing user data:', error);
            this.logout();
        }
    }

    updateUserInterface() {
        // Update user name in header
        const userName = document.getElementById('userName');
        if (userName) {
            userName.textContent = this.currentUser.first_name || this.currentUser.username;
        }
        
        // Show/hide admin elements
        const adminElements = document.querySelectorAll('.admin-only');
        adminElements.forEach(element => {
            element.style.display = this.currentUser.role === 'admin' ? 'block' : 'none';
        });
        
        // Update navigation for admin
        if (this.currentUser.role === 'admin') {
            const usersNavItem = document.querySelector('[data-view="users"]');
            if (usersNavItem) {
                usersNavItem.parentElement.style.display = 'block';
            }
        }
    }

    async logout() {
        try {
            if (this.sessionToken) {
                await fetch('../backend/api/auth_endpoints.php?action=logout', {
                    method: 'POST',
                    headers: {
                        'Authorization': this.sessionToken
                    }
                });
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.closeRealtimeConnection();
            localStorage.removeItem('sessionToken');
            localStorage.removeItem('user');
            window.location.href = 'login.php';
        }
    }

    bindEvents() {
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const view = e.currentTarget.dataset.view;
                this.switchView(view);
            });
        });

        // Mobile menu
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('open');
        });

        // Logout button
        document.getElementById('logoutBtn').addEventListener('click', () => {
            this.logout();
        });

        // Add task button
        document.getElementById('addTaskBtn').addEventListener('click', () => {
            this.openTaskModal();
        });

        // Add project button
        document.getElementById('addProjectBtn').addEventListener('click', () => {
            this.openProjectModal();
        });

        // Add user button (admin only)
        const addUserBtn = document.getElementById('addUserBtn');
        if (addUserBtn) {
            addUserBtn.addEventListener('click', () => {
                this.openUserModal();
            });
        }

        // Task form submission
        document.getElementById('taskForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveTask();
        });

        // Project form submission
        document.getElementById('projectForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveProject();
        });

        // User form submission
        const userForm = document.getElementById('userForm');
        if (userForm) {
            userForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveUser();
            });
        }

        // Modal close buttons
        document.getElementById('closeModal').addEventListener('click', () => {
            this.closeTaskModal();
        });

        document.getElementById('cancelTask').addEventListener('click', () => {
            this.closeTaskModal();
        });

        document.getElementById('closeProjectModal').addEventListener('click', () => {
            this.closeProjectModal();
        });

        document.getElementById('cancelProject').addEventListener('click', () => {
            this.closeProjectModal();
        });

        // User modal close buttons
        const closeUserModal = document.getElementById('closeUserModal');
        const cancelUser = document.getElementById('cancelUser');
        if (closeUserModal) {
            closeUserModal.addEventListener('click', () => {
                this.closeUserModal();
            });
        }
        if (cancelUser) {
            cancelUser.addEventListener('click', () => {
                this.closeUserModal();
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            this.searchTerm = e.target.value.toLowerCase();
            this.filterAndDisplayTasks();
        });

        // Filter controls
        document.getElementById('statusFilter').addEventListener('change', (e) => {
            this.filters.status = e.target.value;
            this.filterAndDisplayTasks();
        });

        document.getElementById('priorityFilter').addEventListener('change', (e) => {
            this.filters.priority = e.target.value;
            this.filterAndDisplayTasks();
        });

        document.getElementById('projectFilter').addEventListener('change', (e) => {
            this.filters.project = e.target.value;
            this.filterAndDisplayTasks();
        });

        document.getElementById('sortBy').addEventListener('change', (e) => {
            this.sortBy = e.target.value;
            this.filterAndDisplayTasks();
        });

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                if (e.target.id === 'taskModal') {
                    this.closeTaskModal();
                } else if (e.target.id === 'projectModal') {
                    this.closeProjectModal();
                } else if (e.target.id === 'userModal') {
                    this.closeUserModal();
                }
            }
        });

        // Close sidebar when clicking on main content (mobile)
        document.querySelector('.main-content').addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                document.querySelector('.sidebar').classList.remove('open');
            }
        });
    }

    switchView(view) {
        // Update navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector(`[data-view="${view}"]`).classList.add('active');

        // Update page title
        const titles = {
            'dashboard': 'Dashboard',
            'all-tasks': 'All Tasks',
            'today': 'Today',
            'upcoming': 'Upcoming',
            'completed': 'Completed',
            'users': 'User Management'
        };
        document.getElementById('pageTitle').textContent = titles[view];

        // Hide all views
        document.querySelectorAll('.view').forEach(view => {
            view.classList.remove('active');
        });

        // Show selected view
        document.getElementById(view).classList.add('active');
        this.currentView = view;

        // Update view content
        this.updateViewContent();

        // Close mobile sidebar
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.remove('open');
        }
    }

    updateViewContent() {
        switch (this.currentView) {
            case 'dashboard':
                this.updateDashboard();
                break;
            case 'all-tasks':
                this.displayTasks('allTasksList', this.filteredTasks);
                break;
            case 'today':
                const todayTasks = this.getTodayTasks();
                this.displayTasks('todayTasksList', todayTasks);
                break;
            case 'upcoming':
                const upcomingTasks = this.getUpcomingTasks();
                this.displayTasks('upcomingTasksList', upcomingTasks);
                break;
            case 'completed':
                const completedTasks = this.tasks.filter(task => task.status === 'completed');
                this.displayTasks('completedTasksList', completedTasks);
                break;
            case 'users':
                this.displayUsers();
                break;
        }
    }

    updateDashboard() {
        // Update statistics
        const stats = this.calculateStats();
        document.getElementById('totalTasks').textContent = stats.total;
        document.getElementById('completedTasks').textContent = stats.completed;
        document.getElementById('pendingTasks').textContent = stats.pending;
        document.getElementById('overdueTasks').textContent = stats.overdue;

        // Update today's tasks
        const todayTasks = this.getTodayTasks().slice(0, 5);
        this.displayTasks('todayTasks', todayTasks);

        // Update upcoming tasks
        const upcomingTasks = this.getUpcomingTasks().slice(0, 5);
        this.displayTasks('upcomingTasks', upcomingTasks);
    }

    calculateStats() {
        const total = this.tasks.length;
        const completed = this.tasks.filter(task => task.status === 'completed').length;
        const pending = this.tasks.filter(task => task.status === 'pending').length;
        const overdue = this.tasks.filter(task => this.isOverdue(task)).length;

        return { total, completed, pending, overdue };
    }

    getTodayTasks() {
        const today = new Date().toISOString().split('T')[0];
        return this.tasks.filter(task => {
            return task.due_date === today || 
                   (task.status === 'pending' && !task.due_date);
        });
    }

    getUpcomingTasks() {
        const today = new Date();
        const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
        
        return this.tasks.filter(task => {
            if (!task.due_date) return false;
            const dueDate = new Date(task.due_date);
            return dueDate > today && dueDate <= nextWeek && task.status === 'pending';
        }).sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
    }

    isOverdue(task) {
        if (!task.due_date || task.status === 'completed') return false;
        const today = new Date().toISOString().split('T')[0];
        return task.due_date < today;
    }

    displayTasks(containerId, tasks) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (tasks.length === 0) {
            container.innerHTML = '<div class="empty-state">No tasks found</div>';
            return;
        }

        container.innerHTML = tasks.map(task => this.createTaskHTML(task)).join('');

        // Bind task events
        this.bindTaskEvents(container);
    }

    createTaskHTML(task) {
        const project = this.projects.find(p => p.id === task.project_id);
        const isOverdue = this.isOverdue(task);
        const tags = task.tags ? task.tags.split(',').map(tag => tag.trim()) : [];

        return `
            <div class="task-item" data-task-id="${task.id}">
                <div class="task-header">
                    <div class="task-checkbox ${task.status === 'completed' ? 'completed' : ''}" 
                         data-task-id="${task.id}">
                        ${task.status === 'completed' ? '<i class="fas fa-check"></i>' : ''}
                    </div>
                    <div class="task-title ${task.status === 'completed' ? 'completed' : ''}">
                        ${this.escapeHtml(task.title)}
                    </div>
                    <div class="task-priority ${task.priority}">
                        ${task.priority}
                    </div>
                    <div class="task-actions">
                        <button class="task-action edit-task" data-task-id="${task.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="task-action delete-task" data-task-id="${task.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                ${task.description ? `<div class="task-description">${this.escapeHtml(task.description)}</div>` : ''}
                <div class="task-meta">
                    ${task.due_date ? `
                        <div class="task-due-date ${isOverdue ? 'overdue' : ''}">
                            <i class="fas fa-calendar"></i>
                            ${this.formatDate(task.due_date)}
                        </div>
                    ` : ''}
                    ${project ? `
                        <div class="task-project">
                            <div class="project-color" style="background-color: ${project.color}"></div>
                            ${this.escapeHtml(project.name)}
                        </div>
                    ` : ''}
                    ${tags.length > 0 ? `
                        <div class="task-tags">
                            ${tags.map(tag => `<span class="task-tag">${this.escapeHtml(tag)}</span>`).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    bindTaskEvents(container) {
        // Checkbox toggles
        container.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', (e) => {
                e.stopPropagation();
                const taskId = parseInt(e.currentTarget.dataset.taskId);
                this.toggleTaskStatus(taskId);
            });
        });

        // Edit buttons
        container.querySelectorAll('.edit-task').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const taskId = parseInt(e.currentTarget.dataset.taskId);
                this.editTask(taskId);
            });
        });

        // Delete buttons
        container.querySelectorAll('.delete-task').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const taskId = parseInt(e.currentTarget.dataset.taskId);
                this.deleteTask(taskId);
            });
        });
    }

    filterAndDisplayTasks() {
        this.filteredTasks = this.tasks.filter(task => {
            // Search filter
            if (this.searchTerm) {
                const searchInTitle = task.title.toLowerCase().includes(this.searchTerm);
                const searchInDescription = task.description && task.description.toLowerCase().includes(this.searchTerm);
                const searchInTags = task.tags && task.tags.toLowerCase().includes(this.searchTerm);
                if (!searchInTitle && !searchInDescription && !searchInTags) {
                    return false;
                }
            }

            // Status filter
            if (this.filters.status && task.status !== this.filters.status) {
                return false;
            }

            // Priority filter
            if (this.filters.priority && task.priority !== this.filters.priority) {
                return false;
            }

            // Project filter
            if (this.filters.project) {
                if (this.filters.project === 'none' && task.project_id) {
                    return false;
                } else if (this.filters.project !== 'none' && task.project_id != this.filters.project) {
                    return false;
                }
            }

            return true;
        });

        // Sort tasks
        this.sortTasks();

        // Update current view
        this.updateViewContent();
    }

    sortTasks() {
        this.filteredTasks.sort((a, b) => {
            switch (this.sortBy) {
                case 'created_desc':
                    return new Date(b.created_at) - new Date(a.created_at);
                case 'created_asc':
                    return new Date(a.created_at) - new Date(b.created_at);
                case 'due_asc':
                    if (!a.due_date && !b.due_date) return 0;
                    if (!a.due_date) return 1;
                    if (!b.due_date) return -1;
                    return new Date(a.due_date) - new Date(b.due_date);
                case 'due_desc':
                    if (!a.due_date && !b.due_date) return 0;
                    if (!a.due_date) return 1;
                    if (!b.due_date) return -1;
                    return new Date(b.due_date) - new Date(a.due_date);
                case 'priority_desc':
                    const priorityOrder = { high: 3, medium: 2, low: 1 };
                    return priorityOrder[b.priority] - priorityOrder[a.priority];
                case 'priority_asc':
                    const priorityOrderAsc = { high: 3, medium: 2, low: 1 };
                    return priorityOrderAsc[a.priority] - priorityOrderAsc[b.priority];
                default:
                    return 0;
            }
        });
    }

    // Modal functions
    openTaskModal(task = null) {
        const modal = document.getElementById('taskModal');
        const form = document.getElementById('taskForm');
        
        if (task) {
            // Editing existing task
            document.getElementById('modalTitle').textContent = 'Edit Task';
            document.getElementById('taskId').value = task.id;
            document.getElementById('taskTitle').value = task.title;
            document.getElementById('taskDescription').value = task.description || '';
            document.getElementById('taskPriority').value = task.priority;
            document.getElementById('taskProject').value = task.project_id || '';
            document.getElementById('taskDueDate').value = task.due_date || '';
            document.getElementById('taskDueTime').value = task.due_time || '';
            document.getElementById('taskTags').value = task.tags || '';
        } else {
            // Creating new task
            document.getElementById('modalTitle').textContent = 'Add New Task';
            form.reset();
            document.getElementById('taskId').value = '';
        }

        // Update project options
        this.updateProjectOptions();

        modal.classList.add('active');
    }

    closeTaskModal() {
        document.getElementById('taskModal').classList.remove('active');
    }

    openProjectModal() {
        const modal = document.getElementById('projectModal');
        document.getElementById('projectForm').reset();
        modal.classList.add('active');
    }

    closeProjectModal() {
        document.getElementById('projectModal').classList.remove('active');
    }

    updateProjectOptions() {
        const projectSelect = document.getElementById('taskProject');
        const filterSelect = document.getElementById('projectFilter');

        // Update task form project options
        projectSelect.innerHTML = '<option value="">No Project</option>';
        this.projects.forEach(project => {
            projectSelect.innerHTML += `<option value="${project.id}">${this.escapeHtml(project.name)}</option>`;
        });

        // Update filter project options
        filterSelect.innerHTML = '<option value="">All Projects</option>';
        filterSelect.innerHTML += '<option value="none">No Project</option>';
        this.projects.forEach(project => {
            filterSelect.innerHTML += `<option value="${project.id}">${this.escapeHtml(project.name)}</option>`;
        });
    }

    // CRUD operations
    async saveTask() {
        const formData = new FormData(document.getElementById('taskForm'));
        const taskData = Object.fromEntries(formData.entries());
        
        // Add assigned user if admin and user is selected
        if (this.currentUser.role === 'admin' && taskData.taskAssignedTo) {
            taskData.assignedTo = taskData.taskAssignedTo;
        }
        
        this.showLoading();

        try {
            const url = taskData.taskId ? '../backend/api/tasks.php' : '../backend/api/tasks.php';
            const method = taskData.taskId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.sessionToken
                },
                body: JSON.stringify(taskData)
            });

            const result = await response.json();

            if (result.success) {
                this.showToast(taskData.taskId ? 'Task updated successfully' : 'Task created successfully', 'success');
                this.closeTaskModal();
                this.loadTasks();
            } else {
                this.showToast(result.message || 'Error saving task', 'error');
            }
        } catch (error) {
            this.showToast('Error saving task', 'error');
            console.error('Error:', error);
        }

        this.hideLoading();
    }

    async saveProject() {
        const formData = new FormData(document.getElementById('projectForm'));
        const projectData = Object.fromEntries(formData.entries());
        
        this.showLoading();

        try {
            const response = await fetch('../backend/api/projects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(projectData)
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('Project created successfully', 'success');
                this.closeProjectModal();
                this.loadProjects();
            } else {
                this.showToast(result.message || 'Error saving project', 'error');
            }
        } catch (error) {
            this.showToast('Error saving project', 'error');
            console.error('Error:', error);
        }

        this.hideLoading();
    }

    async loadTasks() {
        try {
            const response = await fetch('../backend/api/tasks.php', {
                headers: {
                    'Authorization': this.sessionToken
                }
            });
            const result = await response.json();

            if (result.success) {
                this.tasks = result.data.tasks || result.data;
                this.filteredTasks = [...this.tasks];
                this.filterAndDisplayTasks();
            } else if (result.error === 'Authentication required') {
                this.logout();
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
            // Use sample data for demo
            this.loadSampleTasks();
        }
    }

    async loadProjects() {
        try {
            const response = await fetch('../backend/api/projects.php');
            const result = await response.json();

            if (result.success) {
                this.projects = result.data.projects || result.data;
                this.updateProjectsList();
                this.updateProjectOptions();
            }
        } catch (error) {
            console.error('Error loading projects:', error);
            // Use sample data for demo
            this.loadSampleProjects();
        }
    }

    async loadUsers() {
        if (this.currentUser.role !== 'admin') return;
        
        try {
            const response = await fetch('../backend/api/auth_endpoints.php?action=users', {
                headers: {
                    'Authorization': this.sessionToken
                }
            });
            const result = await response.json();

            if (result.success) {
                this.users = result.users || [];
                this.updateUserOptions();
            } else if (result.error === 'Authentication required') {
                this.logout();
            }
        } catch (error) {
            console.error('Error loading users:', error);
            // Use sample data for demo
            this.loadSampleUsers();
        }
    }

    loadSampleTasks() {
        this.tasks = [
            {
                id: 1,
                title: 'Complete project proposal',
                description: 'Finish the quarterly project proposal for the management team',
                status: 'pending',
                priority: 'high',
                due_date: '2025-01-15',
                due_time: '17:00',
                project_id: 1,
                tags: 'work, urgent',
                created_at: '2025-01-10'
            },
            {
                id: 2,
                title: 'Review code changes',
                description: 'Review and approve the latest code changes from the development team',
                status: 'pending',
                priority: 'medium',
                due_date: '2025-01-14',
                project_id: 1,
                tags: 'development, review',
                created_at: '2025-01-10'
            },
            {
                id: 3,
                title: 'Buy groceries',
                description: 'Weekly grocery shopping',
                status: 'completed',
                priority: 'low',
                due_date: '2025-01-13',
                project_id: null,
                tags: 'personal',
                created_at: '2025-01-09'
            },
            {
                id: 4,
                title: 'Team meeting preparation',
                description: 'Prepare agenda and materials for the weekly team meeting',
                status: 'pending',
                priority: 'medium',
                due_date: '2025-01-16',
                project_id: 1,
                tags: 'work, meeting',
                created_at: '2025-01-11'
            }
        ];

        this.filteredTasks = [...this.tasks];
        this.filterAndDisplayTasks();
    }

    loadSampleProjects() {
        this.projects = [
            {
                id: 1,
                name: 'Work Projects',
                color: '#667eea'
            },
            {
                id: 2,
                name: 'Personal',
                color: '#48bb78'
            }
        ];

        this.updateProjectsList();
        this.updateProjectOptions();
    }

    loadSampleUsers() {
        this.users = [
            {
                id: 1,
                username: 'admin',
                first_name: 'Admin',
                last_name: 'User',
                email: 'admin@taskflow.com',
                role: 'admin',
                is_active: 1,
                created_at: '2023-01-01'
            },
            {
                id: 2,
                username: 'john',
                first_name: 'John',
                last_name: 'Doe',
                email: 'john@example.com',
                role: 'user',
                is_active: 1,
                created_at: '2023-01-02'
            },
            {
                id: 3,
                username: 'jane',
                first_name: 'Jane',
                last_name: 'Smith',
                email: 'jane@example.com',
                role: 'user',
                is_active: 1,
                created_at: '2023-01-03'
            }
        ];
        this.updateUserOptions();
    }

    updateProjectsList() {
        const container = document.getElementById('projectsList');
        container.innerHTML = this.projects.map(project => `
            <li class="project-item" data-project-id="${project.id}">
                <div class="project-color" style="background-color: ${project.color}"></div>
                <span>${this.escapeHtml(project.name)}</span>
            </li>
        `).join('');

        // Bind project click events
        container.querySelectorAll('.project-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const projectId = e.currentTarget.dataset.projectId;
                this.filterByProject(projectId);
            });
        });
    }



    filterByProject(projectId) {
        this.filters.project = projectId;
        document.getElementById('projectFilter').value = projectId;
        this.switchView('all-tasks');
        this.filterAndDisplayTasks();
    }

    async toggleTaskStatus(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;

        const newStatus = task.status === 'completed' ? 'pending' : 'completed';

        try {
            const response = await fetch('../backend/api/tasks.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.sessionToken
                },
                body: JSON.stringify({
                    taskId: taskId,
                    status: newStatus
                })
            });

            const result = await response.json();

            if (result.success) {
                task.status = newStatus;
                this.filterAndDisplayTasks();
                this.showToast(`Task marked as ${newStatus}`, 'success');
            } else if (result.error === 'Authentication required') {
                this.logout();
            }
        } catch (error) {
            // For demo purposes, update locally
            task.status = newStatus;
            this.filterAndDisplayTasks();
            this.showToast(`Task marked as ${newStatus}`, 'success');
        }
    }

    editTask(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (task) {
            this.openTaskModal(task);
        }
    }

    async deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task?')) {
            return;
        }

        try {
            const response = await fetch(`../backend/api/tasks.php?id=${taskId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': this.sessionToken
                }
            });

            const result = await response.json();

            if (result.success) {
                this.tasks = this.tasks.filter(t => t.id !== taskId);
                this.filterAndDisplayTasks();
                this.showToast('Task deleted successfully', 'success');
            } else if (result.error === 'Authentication required') {
                this.logout();
            }
        } catch (error) {
            // For demo purposes, delete locally
            this.tasks = this.tasks.filter(t => t.id !== taskId);
            this.filterAndDisplayTasks();
            this.showToast('Task deleted successfully', 'success');
        }
    }

    openUserModal(user = null) {
        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        
        if (user) {
            // Editing existing user
            document.getElementById('userModalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = user.id;
            document.getElementById('userUsername').value = user.username;
            document.getElementById('userFirstName').value = user.first_name || '';
            document.getElementById('userLastName').value = user.last_name || '';
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userStatus').value = user.is_active ? '1' : '0';
            document.getElementById('userPassword').value = '';
        } else {
            // Creating new user
            document.getElementById('userModalTitle').textContent = 'Add New User';
            form.reset();
            document.getElementById('userId').value = '';
        }

        modal.classList.add('active');
    }

    closeUserModal() {
        document.getElementById('userModal').classList.remove('active');
    }

    async saveUser() {
        const formData = new FormData(document.getElementById('userForm'));
        const userData = Object.fromEntries(formData.entries());
        
        // Map form field names to backend expected names
        const mappedData = {
            username: userData.userUsername,
            email: userData.userEmail,
            password: userData.userPassword,
            first_name: userData.userFirstName,
            last_name: userData.userLastName,
            role: userData.userRole,
            is_active: userData.userStatus
        };
        
        // Add ID if editing
        if (userData.userId) {
            mappedData.id = userData.userId;
        }
        
        // Debug: Log the data being sent
        console.log('Form data:', userData);
        console.log('Mapped data:', mappedData);
        
        this.showLoading();

        try {
            const url = userData.userId ? '../backend/api/auth_endpoints.php?action=users/update' : '../backend/api/auth_endpoints.php?action=users';
            const method = userData.userId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.sessionToken
                },
                body: JSON.stringify(mappedData)
            });

            const result = await response.json();

            if (result.success) {
                this.showToast(userData.userId ? 'User updated successfully' : 'User created successfully', 'success');
                this.closeUserModal();
                this.loadUsers();
            } else {
                this.showToast(result.message || 'Error saving user', 'error');
            }
        } catch (error) {
            this.showToast('Error saving user', 'error');
            console.error('Error:', error);
        }

        this.hideLoading();
    }

    editUser(userId) {
        const user = this.users.find(u => u.id === userId);
        if (user) {
            this.openUserModal(user);
        }
    }

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user?')) {
            return;
        }

        this.showLoading();

        try {
            const response = await fetch('../backend/api/auth_endpoints.php?action=users/delete', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.sessionToken
                },
                body: JSON.stringify({ id: userId })
            });

            const result = await response.json();

            if (result.success) {
                this.users = this.users.filter(u => u.id != userId);
                this.updateUserOptions();
                this.displayUsers();
                this.showToast('User deleted successfully', 'success');
            } else {
                this.showToast(result.message || 'Error deleting user', 'error');
            }
        } catch (error) {
            // For demo purposes, delete locally
            this.users = this.users.filter(u => u.id != userId);
            this.updateUserOptions();
            this.displayUsers();
            this.showToast('User deleted successfully', 'success');
        }

        this.hideLoading();
    }

    // Utility functions
    formatDate(dateString) {
        const date = new Date(dateString);
        const today = new Date();
        const tomorrow = new Date(today.getTime() + 24 * 60 * 60 * 1000);

        const dateStr = date.toISOString().split('T')[0];
        const todayStr = today.toISOString().split('T')[0];
        const tomorrowStr = tomorrow.toISOString().split('T')[0];

        if (dateStr === todayStr) return 'Today';
        if (dateStr === tomorrowStr) return 'Tomorrow';

        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showLoading() {
        document.getElementById('loading').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loading').style.display = 'none';
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;

        container.appendChild(toast);

        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    updateUI() {
        this.updateViewContent();
        this.updateProjectsList();
        this.updateProjectOptions();
        this.updateUserOptions();
    }

    updateUserOptions() {
        const userSelect = document.getElementById('taskAssignedTo');
        if (userSelect && this.currentUser.role === 'admin') {
            userSelect.innerHTML = '<option value="">Select User</option>';
            this.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username;
                userSelect.appendChild(option);
            });
        }
    }

    displayUsers() {
        const container = document.getElementById('usersTableBody');
        if (!container) return;

        container.innerHTML = this.users.map(user => `
            <tr>
                <td>${this.escapeHtml(user.username)}</td>
                <td>${this.escapeHtml(user.email)}</td>
                <td>${this.escapeHtml(user.first_name || '')} ${this.escapeHtml(user.last_name || '')}</td>
                <td><span class="user-role ${user.role}">${this.escapeHtml(user.role)}</span></td>
                <td><span class="user-status ${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                <td class="user-actions">
                    <button class="user-action edit" onclick="taskManager.editUser(${user.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="user-action delete" onclick="taskManager.deleteUser(${user.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // Real-time Updates
    initRealtimeUpdates() {
        if (!this.realtimeEnabled || !this.sessionToken) return;
        
        try {
            this.eventSource = new EventSource(`../backend/api/realtime.php?token=${encodeURIComponent(this.sessionToken)}`);
            
            this.eventSource.onopen = () => {
                console.log('Real-time connection established');
            };
            
            this.eventSource.onmessage = (event) => {
                console.log('SSE message:', event.data);
            };
            
            this.eventSource.addEventListener('connected', (event) => {
                console.log('Connected to real-time updates');
            });
            
            this.eventSource.addEventListener('task_update', (event) => {
                const data = JSON.parse(event.data);
                console.log('Task update received:', data);
                this.showToast('Tasks have been updated', 'info');
                this.loadTasks(); // Refresh tasks
            });
            
            this.eventSource.addEventListener('user_update', (event) => {
                const data = JSON.parse(event.data);
                console.log('User update received:', data);
                if (this.currentUser.role === 'admin') {
                    this.showToast('Users have been updated', 'info');
                    this.loadUsers(); // Refresh users
                }
            });
            
            this.eventSource.addEventListener('heartbeat', (event) => {
                // Keep connection alive
            });
            
            this.eventSource.addEventListener('error', (event) => {
                const data = JSON.parse(event.data);
                console.error('Real-time error:', data.error);
                this.showToast(`Real-time error: ${data.error}`, 'error');
            });
            
            this.eventSource.onerror = (error) => {
                console.error('Real-time connection error:', error);
                this.reconnectRealtime();
            };
            
        } catch (error) {
            console.error('Failed to initialize real-time updates:', error);
        }
    }
    
    closeRealtimeConnection() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }
    
    reconnectRealtime() {
        this.closeRealtimeConnection();
        setTimeout(() => {
            this.initRealtimeUpdates();
        }, 5000); // Retry after 5 seconds
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.taskManager = new TaskManager();
});