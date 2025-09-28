<?php
require_once '../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    redirect('../index.php');
}

$page_title = 'Fire Reports';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';
$urgency_filter = $_GET['urgency'] ?? 'all';

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="d-flex" id="wrapper">
        
    <?php include 'includes/sidebar.php'; ?>
    
     
    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="bi bi-bar-chart text-danger me-2"></i>Fire Department Reports</h2>
                            <p class="text-muted">Comprehensive fire incident reports and analytics</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-danger" onclick="exportReport()">
                                <i class="bi bi-download me-1"></i> Export PDF
                            </button>
                            <button class="btn btn-outline-secondary" onclick="printReport()">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Report Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="notified" <?php echo $status_filter == 'notified' ? 'selected' : ''; ?>>Notified</option>
                                <option value="responding" <?php echo $status_filter == 'responding' ? 'selected' : ''; ?>>Responding</option>
                                <option value="on_scene" <?php echo $status_filter == 'on_scene' ? 'selected' : ''; ?>>On Scene</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="urgency" class="form-label">Urgency</label>
                            <select class="form-select" id="urgency" name="urgency">
                                <option value="all" <?php echo $urgency_filter == 'all' ? 'selected' : ''; ?>>All Urgency</option>
                                <option value="low" <?php echo $urgency_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $urgency_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $urgency_filter == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $urgency_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-search me-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

             
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="total-incidents">0</h3>
                                    <p class="fs-6 mb-0">Total Fire Incidents</p>
                                </div>
                                <i class="bi bi-fire fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="resolved-incidents">0</h3>
                                    <p class="fs-6 mb-0">Fires Extinguished</p>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="avg-response-time">0 min</h3>
                                    <p class="fs-6 mb-0">Avg Response Time</p>
                                </div>
                                <i class="bi bi-clock fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="success-rate">0%</h3>
                                    <p class="fs-6 mb-0">Success Rate</p>
                                </div>
                                <i class="bi bi-percent fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

              
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Fire Incidents Trend</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="incidentsTrendChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Incidents by Urgency</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="urgencyChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Response Status</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Top Fire-Prone Barangays</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="barangayChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

              
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Fire Incident Report</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="detailed-report-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Report #</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Response Time</th>
                                    <th>Resolution Time</th>
                                </tr>
                            </thead>
                            <tbody id="detailed-report-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="spinner-border text-danger" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading fire incident reports...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let incidentsTrendChart, urgencyChart, statusChart, barangayChart;

// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

document.addEventListener('DOMContentLoaded', function() {
    loadReportData();
});

function loadReportData() {
    const params = new URLSearchParams(window.location.search);
    
    fetch(`ajax/get_fire_report_data.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSummaryStats(data.summary);
                updateCharts(data.charts);
                updateDetailedTable(data.detailed);
            }
        })
        .catch(error => {
            console.error('Error loading report data:', error);
        });
}

function updateSummaryStats(summary) {
    document.getElementById('total-incidents').textContent = summary.total_incidents || 0;
    document.getElementById('resolved-incidents').textContent = summary.resolved_incidents || 0;
    document.getElementById('avg-response-time').textContent = (summary.avg_response_time || 0) + ' min';
    document.getElementById('success-rate').textContent = (summary.success_rate || 0) + '%';
}

function updateCharts(chartData) {
    // Incidents Trend Chart
    const trendCtx = document.getElementById('incidentsTrendChart').getContext('2d');
    if (incidentsTrendChart) incidentsTrendChart.destroy();
    
    incidentsTrendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: chartData.trend?.labels || [],
            datasets: [{
                label: 'Fire Incidents',
                data: chartData.trend?.data || [],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Urgency Chart
    const urgencyCtx = document.getElementById('urgencyChart').getContext('2d');
    if (urgencyChart) urgencyChart.destroy();
    
    urgencyChart = new Chart(urgencyCtx, {
        type: 'doughnut',
        data: {
            labels: chartData.urgency?.labels || [],
            datasets: [{
                data: chartData.urgency?.data || [],
                backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    if (statusChart) statusChart.destroy();
    
    statusChart = new Chart(statusCtx, {
        type: 'bar',
        data: {
            labels: chartData.status?.labels || [],
            datasets: [{
                label: 'Count',
                data: chartData.status?.data || [],
                backgroundColor: ['#17a2b8', '#ffc107', '#dc3545', '#28a745']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Barangay Chart
    const barangayCtx = document.getElementById('barangayChart').getContext('2d');
    if (barangayChart) barangayChart.destroy();
    
    barangayChart = new Chart(barangayCtx, {
        type: 'bar',
        data: {
            labels: chartData.barangay?.labels || [],
            datasets: [{
                label: 'Fire Incidents',
                data: chartData.barangay?.data || [],
                backgroundColor: '#dc3545'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateDetailedTable(detailed) {
    const tbody = document.getElementById('detailed-report-tbody');
    
    if (detailed && detailed.length > 0) {
        let html = '';
        detailed.forEach(incident => {
            const urgencyClass = getUrgencyClass(incident.urgency_level);
            const statusClass = getStatusClass(incident.response_status);
            
            html += `
                <tr>
                    <td>${new Date(incident.created_at).toLocaleDateString()}</td>
                    <td class="fw-medium">${incident.report_number}</td>
                    <td>${incident.incident_type}</td>
                    <td>${incident.location}<br><small class="text-muted">${incident.barangay || 'N/A'}</small></td>
                    <td><span class="badge ${urgencyClass} rounded-pill">${incident.urgency_level}</span></td>
                    <td><span class="badge ${statusClass} rounded-pill">${getStatusText(incident.response_status)}</span></td>
                    <td>${incident.response_time || 'N/A'}</td>
                    <td>${incident.resolution_time || 'N/A'}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } else {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No fire incidents found for the selected criteria</td></tr>';
    }
}

function getUrgencyClass(urgency) {
    switch(urgency?.toLowerCase()) {
        case 'low': return 'bg-success';
        case 'medium': return 'bg-warning';
        case 'high': return 'bg-danger';
        case 'critical': return 'bg-dark';
        default: return 'bg-secondary';
    }
}

function getStatusClass(status) {
    switch(status) {
        case 'notified': return 'bg-info';
        case 'responding': return 'bg-warning';
        case 'on_scene': return 'bg-danger';
        case 'resolved': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'notified': return 'Notified';
        case 'responding': return 'Responding';
        case 'on_scene': return 'On Scene';
        case 'resolved': return 'Resolved';
        default: return 'Unknown';
    }
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    window.open(`ajax/export_fire_report.php?${params.toString()}`, '_blank');
}

function printReport() {
    window.print();
}
</script>

<?php include '../includes/footer.php'; ?>
