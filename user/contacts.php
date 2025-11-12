<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Emergency Contacts';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Emergency contacts data
$emergency_contacts = [
    [
        'category' => 'Emergency Services',
        'contacts' => [
            ['name' => 'Emergency Hotline', 'number' => '911', 'description' => 'National Emergency Hotline', 'icon' => 'telephone-fill', 'color' => 'danger'],
            ['name' => 'MDRRMO Agoncillo', 'number' => '+63 994 295 8621', 'description' => '24/7 Emergency Response', 'icon' => 'shield-fill', 'color' => 'danger'],
            ['name' => 'Fire Department', 'number' => '+63 981 480 5828', 'description' => 'Fire and Rescue Services', 'icon' => 'fire', 'color' => 'warning'],
            ['name' => 'Police Station', 'number' => '+63 915 261 9656', 'description' => 'Law Enforcement', 'icon' => 'shield-check', 'color' => 'primary'],
            ['name' => 'PCG Agoncillo Sub Station', 'number' => '+63 977 385 1215', 'description' => 'Coast Guard Services', 'icon' => 'shield-check', 'color' => 'primary'],
            ['name' => 'Ambulance Service', 'number' => '+63 927 212 7017', 'description' => 'Medical Emergency', 'icon' => 'hospital', 'color' => 'success']
        ]
    ],
    [
        'category' => 'Utilities & Services',
        'contacts' => [
            ['name' => 'BATELEC 1', 'number' => '+63 916 590 5512', 'description' => 'Power Outages', 'icon' => 'lightning-fill', 'color' => 'warning'],
            ['name' => 'PRIME WATER', 'number' => '+63 950 9114632', 'description' => 'Water Supply Issues', 'icon' => 'droplet-fill', 'color' => 'primary']
        ]
    ],
    [
        'category' => 'Government Offices',
        'contacts' => [
            ['name' => 'Municipal Hall', 'number' => '(043) 773 - 6880 LOC. 116', 'description' => 'Local Government', 'icon' => 'building-fill', 'color' => 'primary'],
            ['name' => 'Engineering Office', 'number' => '+63 977 805 5105', 'description' => 'Infrastructure & Public Works', 'icon' => 'tools', 'color' => 'primary'],
            ['name' => 'MSWD Office', 'number' => '+63 917 837 5254', 'description' => 'Social Services', 'icon' => 'people-fill', 'color' => 'primary']
        ]
    ]
];

include '../includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="../assets/img/logo.png" alt="Agoncillo Logo" class="me-2" style="height: 40px;">
            <span>MERS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill me-1"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="alerts.php"><i class="bi bi-bell-fill me-1"></i> Alerts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="map.php"><i class="bi bi-map-fill me-1"></i> Evacuation Map</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <!-- Added My Reports link to navbar -->
                <li class="nav-item">
                    <a class="nav-link" href="my-reports.php"><i class="bi bi-file-earmark-text-fill me-1"></i> My Reports</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                         <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                         <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-telephone-fill me-2 text-primary"></i>Emergency Contacts</h2>
            <p class="text-muted mb-0">Important phone numbers for emergency situations in Agoncillo, Batangas</p>
        </div>
        
    </div>

    <!-- Emergency Alert -->
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
        <div class="flex-grow-1">
            <strong>In case of immediate emergency, call:</strong>
            <div class="d-flex flex-wrap gap-3 mt-2">
                <a href="tel:+639123456789" class="btn btn-danger btn-sm">
                    <i class="bi bi-telephone-fill me-1"></i>MDRRMO: +63 994 295 8621
                </a>
                <a href="tel:911" class="btn btn-danger btn-sm">
                    <i class="bi bi-telephone-fill me-1"></i>Emergency: 911
                </a>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchContacts" placeholder="Search contacts...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterCategory">
                        <option value="">All Categories</option>
                        <option value="Emergency Services">Emergency Services</option>
                        <option value="Utilities & Services">Utilities & Services</option>
                        <option value="Government Offices">Government Offices</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Contacts List -->
    <?php foreach ($emergency_contacts as $category): ?>
    <div class="contact-category mb-4" data-category="<?php echo $category['category']; ?>">
        <h4 class="mb-3">
            <i class="bi bi-folder-fill me-2 text-primary"></i><?php echo $category['category']; ?>
        </h4>
        <div class="row">
            <?php foreach ($category['contacts'] as $contact): ?>
            <div class="col-lg-6 mb-3">
                <div class="card shadow-sm h-100 contact-card" data-name="<?php echo strtolower($contact['name']); ?>" data-description="<?php echo strtolower($contact['description']); ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="bg-<?php echo $contact['color']; ?> text-white rounded-circle p-3 me-3 flex-shrink-0">
                                <i class="bi bi-<?php echo $contact['icon']; ?> fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?php echo $contact['name']; ?></h5>
                                <p class="text-muted mb-2"><?php echo $contact['description']; ?></p>
                                <div class="d-flex align-items-center justify-content-between">
                                    <a href="tel:<?php echo $contact['number']; ?>" class="text-decoration-none fw-bold text-primary">
                                        <i class="bi bi-telephone-fill me-1"></i><?php echo $contact['number']; ?>
                                    </a>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                onclick="callNumber('<?php echo $contact['number']; ?>')" title="Call">
                                            <i class="bi bi-telephone"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="sendSMS('<?php echo $contact['number']; ?>')" title="SMS">
                                            <i class="bi bi-chat-text"></i>
                                        </button>
                                      
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- No Results Message -->
    <div id="noResults" class="text-center py-5" style="display: none;">
        <i class="bi bi-search display-1 text-muted mb-3"></i>
        <h4 class="text-muted">No contacts found</h4>
        <p class="text-muted">Try adjusting your search or filter criteria</p>
    </div>

    <!-- Emergency Tips -->
    <div class="card shadow-sm mt-5">
        <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">
                <i class="bi bi-lightbulb-fill me-2"></i>Emergency Tips
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="bi bi-1-circle-fill me-2 text-primary"></i>Stay Calm</h6>
                    <p class="mb-3">Keep calm and speak clearly when calling emergency services.</p>
                    
                    <h6><i class="bi bi-2-circle-fill me-2 text-primary"></i>Provide Location</h6>
                    <p class="mb-3">Give your exact location, including barangay and landmarks.</p>
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-3-circle-fill me-2 text-primary"></i>Describe Emergency</h6>
                    <p class="mb-3">Clearly describe the nature and severity of the emergency.</p>
                    
                    <h6><i class="bi bi-4-circle-fill me-2 text-primary"></i>Follow Instructions</h6>
                    <p class="mb-3">Listen carefully and follow the dispatcher's instructions.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchContacts').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    filterContacts();
});

// Category filter
document.getElementById('filterCategory').addEventListener('change', function() {
    filterContacts();
});

function filterContacts() {
    const searchTerm = document.getElementById('searchContacts').value.toLowerCase();
    const selectedCategory = document.getElementById('filterCategory').value;
    
    const categories = document.querySelectorAll('.contact-category');
    let hasVisibleContacts = false;
    
    categories.forEach(category => {
        const categoryName = category.dataset.category;
        let categoryHasVisible = false;
        
        // Check if category matches filter
        if (selectedCategory && selectedCategory !== categoryName) {
            category.style.display = 'none';
            return;
        }
        
        const contacts = category.querySelectorAll('.contact-card');
        contacts.forEach(contact => {
            const name = contact.dataset.name;
            const description = contact.dataset.description;
            
            if (searchTerm === '' || name.includes(searchTerm) || description.includes(searchTerm)) {
                contact.parentElement.style.display = 'block';
                categoryHasVisible = true;
                hasVisibleContacts = true;
            } else {
                contact.parentElement.style.display = 'none';
            }
        });
        
        category.style.display = categoryHasVisible ? 'block' : 'none';
    });
    
    // Show/hide no results message
    document.getElementById('noResults').style.display = hasVisibleContacts ? 'none' : 'block';
}

function callNumber(number) {
    if (confirm(`Call ${number}?`)) {
        window.location.href = `tel:${number}`;
    }
}

function sendSMS(number) {
    if (confirm(`Send SMS to ${number}?`)) {
        window.location.href = `sms:${number}`;
    }
}


</script>

<?php include '../includes/footer.php'; ?>
