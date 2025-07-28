const fs = require("fs")
const path = require("path")

// Create the .bsdesign project structure
const projectStructure = {
  "agoncillo-disaster-system.bsdesign": {
    "project.json": {
      name: "MDRRMO Agoncillo Emergency Response System",
      version: "1.0.0",
      description: "Disaster Risk Reduction Management System for Municipality of Agoncillo, Batangas",
      author: "MDRRMO Agoncillo",
      created: new Date().toISOString(),
      pages: [
        {
          name: "index.html",
          title: "Login - MDRRMO Agoncillo",
          description: "Emergency Response System Login Page",
        },
        {
          name: "user-dashboard.html",
          title: "Resident Dashboard - MDRRMO Agoncillo",
          description: "Resident Emergency Dashboard",
        },
        {
          name: "admin-dashboard.html",
          title: "Admin Dashboard - MDRRMO Agoncillo",
          description: "MDRRMO Staff Administration Dashboard",
        },
      ],
      assets: {
        css: ["styles/main.css", "styles/bootstrap.min.css"],
        js: ["js/main.js", "js/bootstrap.bundle.min.js"],
        images: ["images/logo.png", "images/agoncillo-seal.png"],
        fonts: [],
      },
    },
  },
}

// Create HTML files based on the React components
const htmlFiles = {
  "index.html": `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MDRRMO Agoncillo - Emergency Response System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm border-bottom">
        <div class="container py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-danger p-2 rounded me-3">
                        <i class="fas fa-shield-alt text-white fs-4"></i>
                    </div>
                    <div>
                        <h1 class="h4 mb-0 text-dark">MDRRMO Agoncillo</h1>
                        <small class="text-muted">Disaster Risk Reduction Management</small>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row align-items-center">
            <!-- Left Side - Information -->
            <div class="col-lg-6 mb-5 mb-lg-0">
                <h2 class="display-5 fw-bold text-dark mb-4">Emergency Response System</h2>
                <p class="lead text-muted mb-4">
                    Protecting the Municipality of Agoncillo, Batangas through real-time alerts, 
                    GIS mapping, and coordinated emergency response.
                </p>
                
                <div class="row g-4">
                    <div class="col-sm-6">
                        <div class="d-flex">
                            <i class="fas fa-exclamation-triangle text-danger me-3 mt-1"></i>
                            <div>
                                <h5 class="fw-semibold">Instant Alerts</h5>
                                <small class="text-muted">SMS & Email notifications for emergencies</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex">
                            <i class="fas fa-map-marker-alt text-primary me-3 mt-1"></i>
                            <div>
                                <h5 class="fw-semibold">GIS Mapping</h5>
                                <small class="text-muted">Real-time evacuation routes & hazard zones</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex">
                            <i class="fas fa-mobile-alt text-success me-3 mt-1"></i>
                            <div>
                                <h5 class="fw-semibold">GPS Tracking</h5>
                                <small class="text-muted">Precise location for emergency reports</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex">
                            <i class="fas fa-file-alt text-info me-3 mt-1"></i>
                            <div>
                                <h5 class="fw-semibold">Report System</h5>
                                <small class="text-muted">Verified incident reporting & tracking</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="col-lg-6">
                <div class="card shadow-lg border-0 mx-auto" style="max-width: 400px;">
                    <div class="card-header bg-white text-center py-4">
                        <h3 class="card-title mb-2">Sign In</h3>
                        <p class="text-muted mb-0">Access your emergency response dashboard</p>
                    </div>
                    <div class="card-body p-4">
                        <!-- Login Tabs -->
                        <ul class="nav nav-pills nav-fill mb-4" id="loginTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="resident-tab" data-bs-toggle="pill" data-bs-target="#resident" type="button" role="tab">Resident</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admin-tab" data-bs-toggle="pill" data-bs-target="#admin" type="button" role="tab">MDRRMO Staff</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="loginTabsContent">
                            <!-- Resident Login -->
                            <div class="tab-pane fade show active" id="resident" role="tabpanel">
                                <form>
                                    <div class="mb-3">
                                        <label for="residentPhone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="residentPhone" placeholder="+63 9XX XXX XXXX">
                                    </div>
                                    <div class="mb-3">
                                        <label for="residentPassword" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="residentPassword">
                                    </div>
                                    <a href="user-dashboard.html" class="btn btn-primary w-100 mb-3">Sign In as Resident</a>
                                    <div class="text-center">
                                        <a href="#" class="text-decoration-none small">Don't have an account? Register here</a>
                                    </div>
                                </form>
                            </div>

                            <!-- Admin Login -->
                            <div class="tab-pane fade" id="admin" role="tabpanel">
                                <form>
                                    <div class="mb-3">
                                        <label for="adminUsername" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="adminUsername" placeholder="MDRRMO Staff ID">
                                    </div>
                                    <div class="mb-3">
                                        <label for="adminPassword" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="adminPassword">
                                    </div>
                                    <a href="admin-dashboard.html" class="btn btn-danger w-100">Sign In as MDRRMO Staff</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-semibold mb-3">Emergency Hotlines</h5>
                    <div class="small">
                        <p class="mb-1">MDRRMO Agoncillo: (043) 123-4567</p>
                        <p class="mb-1">Police: 117</p>
                        <p class="mb-1">Fire Department: 116</p>
                        <p class="mb-0">Medical Emergency: 911</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="fw-semibold mb-3">Municipality of Agoncillo</h5>
                    <p class="small text-light">
                        Batangas Province, Philippines<br>
                        Disaster Risk Reduction & Management Office
                    </p>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-semibold mb-3">System Status</h5>
                    <div class="d-flex align-items-center">
                        <div class="bg-success rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                        <span class="small">All systems operational</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>`,

  "user-dashboard.html": `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - MDRRMO Agoncillo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm border-bottom">
        <div class="container py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-primary p-2 rounded me-3">
                        <i class="fas fa-shield-alt text-white"></i>
                    </div>
                    <div>
                        <h1 class="h5 mb-0">Resident Dashboard</h1>
                        <small class="text-muted">Welcome, Juan Dela Cruz</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-bell me-1"></i> Notifications (3)
                    </button>
                    <a href="index.html" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-home me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Emergency Alert Banner -->
    <div class="container mt-3">
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-3"></i>
            <div>
                <h6 class="alert-heading mb-1">Weather Advisory</h6>
                <p class="mb-0 small">Moderate to heavy rainfall expected in the next 6 hours. Stay alert for possible flooding.</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="emergency-tab" data-bs-toggle="tab" data-bs-target="#emergency" type="button" role="tab">Emergency Report</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="map-tab" data-bs-toggle="tab" data-bs-target="#map" type="button" role="tab">GIS Map</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">Notifications</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">History</button>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabsContent">
            <!-- Emergency Report Tab -->
            <div class="tab-pane fade show active" id="emergency" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                    Report Emergency
                                </h5>
                                <small class="text-muted">Report incidents happening in your area. Your GPS location will be automatically captured.</small>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="mb-3">
                                        <label for="emergencyType" class="form-label">Emergency Type</label>
                                        <select class="form-select" id="emergencyType">
                                            <option value="">Select emergency type</option>
                                            <option value="fire">Fire</option>
                                            <option value="flood">Flood</option>
                                            <option value="accident">Road Accident</option>
                                            <option value="landslide">Landslide</option>
                                            <option value="earthquake">Earthquake</option>
                                            <option value="medical">Medical Emergency</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" rows="4" placeholder="Describe the emergency situation in detail..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Current Location</label>
                                        <div class="alert alert-success d-flex align-items-center py-2">
                                            <i class="fas fa-location-arrow text-success me-2"></i>
                                            <small>GPS Location: 13.9094° N, 120.9200° E (Agoncillo, Batangas)</small>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Upload Photo/Video Evidence</label>
                                        <div class="border border-2 border-dashed rounded p-4 text-center">
                                            <i class="fas fa-camera text-muted fs-2 mb-2"></i>
                                            <p class="text-muted mb-2">Click to take photo or upload file</p>
                                            <button type="button" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-upload me-1"></i> Choose File
                                            </button>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Submit Emergency Report
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Emergency Contacts</h5>
                                <small class="text-muted">Tap to call emergency services directly</small>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-danger text-start">
                                        <i class="fas fa-phone text-danger me-3"></i>
                                        MDRRMO Agoncillo: (043) 123-4567
                                    </button>
                                    <button class="btn btn-outline-primary text-start">
                                        <i class="fas fa-phone text-primary me-3"></i>
                                        Police Station: 117
                                    </button>
                                    <button class="btn btn-outline-warning text-start">
                                        <i class="fas fa-phone text-warning me-3"></i>
                                        Fire Department: 116
                                    </button>
                                    <button class="btn btn-outline-success text-start">
                                        <i class="fas fa-phone text-success me-3"></i>
                                        Medical Emergency: 911
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other tabs content would continue here... -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>`,

  "admin-dashboard.html": `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MDRRMO Agoncillo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="bg-danger text-white shadow">
        <div class="container py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-white p-2 rounded me-3">
                        <i class="fas fa-shield-alt text-danger"></i>
                    </div>
                    <div>
                        <h1 class="h5 mb-0">MDRRMO Admin Dashboard</h1>
                        <small class="text-light">Municipality of Agoncillo, Batangas</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="fw-semibold">Admin: Maria Santos</div>
                        <small class="text-light">MDRRMO Officer</small>
                    </div>
                    <a href="index.html" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-home me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Stats Overview -->
    <div class="container py-4">
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle text-danger fs-1 mb-2"></i>
                        <h3 class="text-danger">12</h3>
                        <p class="text-muted mb-0">Active Incidents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users text-primary fs-1 mb-2"></i>
                        <h3 class="text-primary">2,847</h3>
                        <p class="text-muted mb-0">Registered Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-paper-plane text-success fs-1 mb-2"></i>
                        <h3 class="text-success">156</h3>
                        <p class="text-muted mb-0">Alerts Sent Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock text-info fs-1 mb-2"></i>
                        <h3 class="text-info">6.2m</h3>
                        <p class="text-muted mb-0">Response Time Avg</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="incidents-tab" data-bs-toggle="tab" data-bs-target="#incidents" type="button" role="tab">Incident Reports</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">Send Alerts</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="gis-tab" data-bs-toggle="tab" data-bs-target="#gis" type="button" role="tab">GIS Management</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">User Management</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">Generate Reports</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">Analytics</button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            <!-- Incident Reports Tab -->
            <div class="tab-pane fade show active" id="incidents" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                            Incident Reports Management
                        </h5>
                        <small class="text-muted">Review and manage emergency reports from residents</small>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <select class="form-select">
                                    <option>All Reports</option>
                                    <option>Pending</option>
                                    <option>In Progress</option>
                                    <option>Resolved</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select">
                                    <option>All Types</option>
                                    <option>Fire</option>
                                    <option>Flood</option>
                                    <option>Accident</option>
                                    <option>Medical</option>
                                </select>
                            </div>
                        </div>

                        <!-- Incident List -->
                        <div class="space-y-3">
                            <div class="border border-danger bg-danger bg-opacity-10 rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="mb-2">
                                            <span class="badge bg-danger me-2">Critical</span>
                                            <span class="badge bg-outline-secondary me-2">Fire</span>
                                            <small class="text-muted">Report #2024-001234</small>
                                        </div>
                                        <h6 class="fw-bold text-danger">House Fire - Residential Area</h6>
                                        <p class="small text-danger mb-2">Large fire reported at 123 Rizal Street, Poblacion. Multiple families affected.</p>
                                        <div class="row small">
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Reporter:</strong> Juan Dela Cruz</p>
                                                <p class="mb-0"><strong>Phone:</strong> +63 917 123 4567</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Time:</strong> Dec 11, 2024 - 3:45 PM</p>
                                                <p class="mb-0"><strong>GPS:</strong> 13.9094° N, 120.9200° E</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ms-3">
                                        <button class="btn btn-outline-primary btn-sm me-2">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-danger btn-sm">Dispatch</button>
                                    </div>
                                </div>
                            </div>
                            <!-- More incident items would be here... -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other tabs content would continue here... -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>`,
}

// Create CSS file
const cssContent = `
/* Custom styles for MDRRMO Agoncillo Emergency Response System */

:root {
    --primary-red: #dc3545;
    --primary-blue: #0d6efd;
    --success-green: #198754;
    --warning-yellow: #ffc107;
    --danger-red: #dc3545;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.bg-gradient-emergency {
    background: linear-gradient(135deg, #fee2e2 0%, #ffffff 50%, #dbeafe 100%);
}

.card {
    border: none;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.btn {
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.alert {
    border-radius: 0.75rem;
    border: none;
}

.nav-tabs .nav-link {
    border-radius: 0.5rem 0.5rem 0 0;
    border: none;
    color: #6b7280;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    background-color: #ffffff;
    color: var(--primary-blue);
    border-bottom: 2px solid var(--primary-blue);
}

.form-control, .form-select {
    border-radius: 0.5rem;
    border: 1px solid #d1d5db;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.badge {
    font-weight: 500;
    padding: 0.375rem 0.75rem;
}

.text-emergency {
    color: var(--danger-red) !important;
}

.bg-emergency {
    background-color: var(--danger-red) !important;
}

.border-emergency {
    border-color: var(--danger-red) !important;
}

/* Emergency status indicators */
.status-critical {
    border-left: 4px solid var(--danger-red);
}

.status-high {
    border-left: 4px solid var(--warning-yellow);
}

.status-medium {
    border-left: 4px solid var(--primary-blue);
}

.status-low {
    border-left: 4px solid var(--success-green);
}

/* Map container */
.map-container {
    height: 400px;
    background-color: #f8f9fa;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Progress bars for analytics */
.progress {
    height: 8px;
    border-radius: 4px;
}

.progress-bar {
    border-radius: 4px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .btn-group-vertical .btn {
        margin-bottom: 0.5rem;
    }
}

/* Animation for emergency alerts */
@keyframes pulse-emergency {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.alert-emergency {
    animation: pulse-emergency 2s infinite;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
`

// Create JavaScript file
const jsContent = `
// MDRRMO Agoncillo Emergency Response System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Emergency form validation
    const emergencyForm = document.querySelector('#emergencyForm');
    if (emergencyForm) {
        emergencyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            validateEmergencyReport();
        });
    }

    // GPS location simulation
    simulateGPSLocation();

    // Real-time clock
    updateClock();
    setInterval(updateClock, 1000);

    // Auto-refresh incident reports every 30 seconds
    if (document.querySelector('#incidents')) {
        setInterval(refreshIncidentReports, 30000);
    }
});

// Emergency report validation
function validateEmergencyReport() {
    const emergencyType = document.querySelector('#emergencyType');
    const description = document.querySelector('#description');
    
    let isValid = true;
    
    if (!emergencyType || emergencyType.value === '') {
        showAlert('Please select an emergency type', 'warning');
        isValid = false;
    }
    
    if (!description || description.value.trim() === '') {
        showAlert('Please provide a description of the emergency', 'warning');
        isValid = false;
    }
    
    if (isValid) {
        submitEmergencyReport();
    }
}

// Submit emergency report
function submitEmergencyReport() {
    // Show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        showAlert('Emergency report submitted successfully! Help is on the way.', 'success');
        
        // Reset form
        document.querySelector('#emergencyForm').reset();
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        // Add to history (simulation)
        addToReportHistory();
    }, 2000);
}

// GPS location simulation
function simulateGPSLocation() {
    const locationElements = document.querySelectorAll('.gps-location');
    locationElements.forEach(element => {
        // Simulate slight GPS variations
        const baseLat = 13.9094;
        const baseLng = 120.9200;
        const variation = 0.0001;
        
        const lat = (baseLat + (Math.random() - 0.5) * variation).toFixed(4);
        const lng = (baseLng + (Math.random() - 0.5) * variation).toFixed(4);
        
        element.textContent = \`GPS Location: \${lat}° N, \${lng}° E (Agoncillo, Batangas)\`;
    });
}

// Update real-time clock
function updateClock() {
    const clockElements = document.querySelectorAll('.real-time-clock');
    const now = new Date();
    const timeString = now.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    clockElements.forEach(element => {
        element.textContent = timeString;
    });
}

// Show alert messages
function showAlert(message, type = 'info') {
    const alertContainer = document.querySelector('#alertContainer') || createAlertContainer();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = \`alert alert-\${type} alert-dismissible fade show\`;
    alertDiv.innerHTML = \`
        \${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    \`;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Create alert container if it doesn't exist
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Refresh incident reports (simulation)
function refreshIncidentReports() {
    const incidentCount = document.querySelector('.incident-count');
    if (incidentCount) {
        // Simulate real-time updates
        const currentCount = parseInt(incidentCount.textContent);
        const change = Math.floor(Math.random() * 3) - 1; // -1, 0, or 1
        const newCount = Math.max(0, currentCount + change);
        incidentCount.textContent = newCount;
    }
}

// Add to report history (simulation)
function addToReportHistory() {
    const historyContainer = document.querySelector('#reportHistory');
    if (historyContainer) {
        const reportItem = document.createElement('div');
        reportItem.className = 'border rounded p-3 mb-3';
        reportItem.innerHTML = \`
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="fw-bold">Emergency Report</h6>
                    <p class="small text-muted mb-1">Just submitted</p>
                    <span class="badge bg-warning">Under Review</span>
                </div>
                <small class="text-muted">\${new Date().toLocaleTimeString()}</small>
            </div>
        \`;
        
        historyContainer.insertBefore(reportItem, historyContainer.firstChild);
    }
}

// Send alert functionality (Admin)
function sendEmergencyAlert() {
    const alertType = document.querySelector('#alertType');
    const alertTitle = document.querySelector('#alertTitle');
    const alertMessage = document.querySelector('#alertMessage');
    const targetArea = document.querySelector('#targetArea');
    
    if (!alertType.value || !alertTitle.value || !alertMessage.value) {
        showAlert('Please fill in all required fields', 'warning');
        return;
    }
    
    // Show confirmation modal
    if (confirm('Are you sure you want to send this emergency alert to all residents?')) {
        // Simulate sending alert
        const sendBtn = document.querySelector('#sendAlertBtn');
        const originalText = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        sendBtn.disabled = true;
        
        setTimeout(() => {
            showAlert(\`Emergency alert sent successfully to \${targetArea.options[targetArea.selectedIndex].text}\`, 'success');
            
            // Reset form
            document.querySelector('#alertForm').reset();
            
            // Reset button
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
            
            // Update sent alerts counter
            updateSentAlertsCounter();
        }, 2000);
    }
}

// Update sent alerts counter
function updateSentAlertsCounter() {
    const counter = document.querySelector('.alerts-sent-today');
    if (counter) {
        const currentCount = parseInt(counter.textContent);
        counter.textContent = currentCount + 1;
    }
}

// Generate report functionality
function generateReport() {
    const reportType = document.querySelector('#reportType');
    const dateFrom = document.querySelector('#dateFrom');
    const dateTo = document.querySelector('#dateTo');
    
    if (!reportType.value) {
        showAlert('Please select a report type', 'warning');
        return;
    }
    
    // Show loading state
    const generateBtn = document.querySelector('#generateReportBtn');
    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
    generateBtn.disabled = true;
    
    // Simulate report generation
    setTimeout(() => {
        showAlert('Report generated successfully!', 'success');
        
        // Reset button
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
        
        // Simulate download
        const link = document.createElement('a');
        link.href = '#';
        link.download = \`\${reportType.value}-report-\${new Date().toISOString().split('T')[0]}.pdf\`;
        link.click();
    }, 3000);
}

// Map layer toggle functionality
function toggleMapLayer(layerName, checkbox) {
    console.log(\`Toggling \${layerName} layer: \${checkbox.checked}\`);
    // In a real implementation, this would interact with the GIS mapping system
    showAlert(\`\${layerName} layer \${checkbox.checked ? 'enabled' : 'disabled'}\`, 'info');
}

// User verification functionality
function verifyUser(userId, action) {
    const confirmMessage = action === 'approve' ? 
        'Are you sure you want to approve this user?' : 
        'Are you sure you want to reject this user?';
    
    if (confirm(confirmMessage)) {
        // Simulate API call
        setTimeout(() => {
            showAlert(\`User \${action}d successfully\`, 'success');
            // Update UI to reflect the change
            const userRow = document.querySelector(\`[data-user-id="\${userId}"]\`);
            if (userRow) {
                const badge = userRow.querySelector('.badge');
                if (action === 'approve') {
                    badge.className = 'badge bg-success';
                    badge.textContent = 'Verified';
                } else {
                    badge.className = 'badge bg-danger';
                    badge.textContent = 'Rejected';
                }
            }
        }, 1000);
    }
}

// Export
