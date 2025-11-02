<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Safety Tips & Guidelines';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Safety tips data organized by disaster type
$safety_tips = [
    'flood' => [
        'title' => 'Flood Safety',
        'icon' => 'water',
        'color' => 'primary',
        'before' => [
            'Know your evacuation routes and shelter locations',
            'Keep emergency supplies in a waterproof container',
            'Stay informed about weather conditions',
            'Avoid building in flood-prone areas',
            'Install sump pumps and backup power'
        ],
        'during' => [
            'Move to higher ground immediately',
            'Avoid walking or driving through flood waters',
            'Stay away from downed power lines',
            'Listen to emergency broadcasts',
            'Do not drink flood water'
        ],
        'after' => [
            'Wait for authorities to declare area safe',
            'Avoid flood waters - may be contaminated',
            'Document damage with photos',
            'Clean and disinfect everything touched by flood water',
            'Check for structural damage before entering buildings'
        ]
    ],
    'earthquake' => [
        'title' => 'Earthquake Safety',
        'icon' => 'house-crack',
        'color' => 'warning',
        'before' => [
            'Secure heavy furniture and appliances',
            'Identify safe spots in each room (under sturdy tables)',
            'Practice drop, cover, and hold on drills',
            'Keep emergency supplies accessible',
            'Know how to turn off gas, water, and electricity'
        ],
        'during' => [
            'Drop, cover, and hold on if indoors',
            'Stay away from windows and heavy objects',
            'If outdoors, move away from buildings and trees',
            'If driving, pull over and stay in vehicle',
            'Do not run outside during shaking'
        ],
        'after' => [
            'Check for injuries and provide first aid',
            'Inspect home for damage',
            'Be prepared for aftershocks',
            'Stay out of damaged buildings',
            'Use flashlights, not candles'
        ]
    ],
    'fire' => [
        'title' => 'Fire Safety',
        'icon' => 'fire',
        'color' => 'danger',
        'before' => [
            'Install smoke detectors and check batteries regularly',
            'Create and practice a fire escape plan',
            'Keep fire extinguishers in key locations',
            'Clear vegetation around your home',
            'Store flammable materials safely'
        ],
        'during' => [
            'Get out fast and stay out',
            'Crawl low under smoke',
            'Feel doors before opening them',
            'Close doors behind you as you escape',
            'Call 911 from outside'
        ],
        'after' => [
            'Do not enter damaged buildings',
            'Watch for hot spots that may flare up',
            'Check with authorities before returning home',
            'Document damage for insurance',
            'Be careful of structural damage'
        ]
    ],
    'typhoon' => [
        'title' => 'Typhoon Safety',
        'icon' => 'tornado',
        'color' => 'info',
        'before' => [
            'Monitor weather reports and warnings',
            'Secure outdoor furniture and objects',
            'Stock up on emergency supplies',
            'Charge electronic devices',
            'Trim trees near your home'
        ],
        'during' => [
            'Stay indoors and away from windows',
            'Go to the lowest floor and interior room',
            'Avoid using electrical appliances',
            'Listen to battery-powered radio for updates',
            'Do not go outside during the eye of the storm'
        ],
        'after' => [
            'Wait for official all-clear before going outside',
            'Watch for fallen power lines and debris',
            'Avoid driving through flooded roads',
            'Check on neighbors and family',
            'Report damage to authorities'
        ]
    ],
    'landslide' => [
        'title' => 'Landslide Safety',
        'icon' => 'mountain',
        'color' => 'secondary',
        'before' => [
            'Know the landslide warning signs',
            'Have an evacuation plan ready',
            'Avoid building on steep slopes',
            'Plant ground cover on slopes',
            'Install proper drainage systems'
        ],
        'during' => [
            'Move away from the path of the landslide',
            'Run to the nearest high ground',
            'If escape is not possible, curl into a ball',
            'Protect your head and vital organs',
            'Stay alert for flooding after landslides'
        ],
        'after' => [
            'Stay away from the slide area',
            'Watch for additional slides',
            'Check for injured and trapped persons',
            'Report broken utility lines',
            'Replant damaged ground as soon as possible'
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
            <h2><i class="bi bi-shield-check me-2 text-primary"></i>Safety Tips & Guidelines</h2>
            <p class="text-muted mb-0">Essential safety information for disaster preparedness and response</p>
        </div>
        <!-- <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="downloadGuide()">
                <i class="bi bi-download me-1"></i>Download Guide
            </button>
            <button class="btn btn-primary" onclick="shareGuide()">
                <i class="bi bi-share me-1"></i>Share
            </button>
        </div> -->
    </div>

    <!-- Quick Navigation -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Quick Navigation</h5>
            <div class="row">
                <?php foreach ($safety_tips as $key => $tip): ?>
                <div class="col-md-2 col-sm-4 col-6 mb-2">
                    <a href="#<?php echo $key; ?>" class="btn btn-outline-<?php echo $tip['color']; ?> w-100 btn-sm">
                        <i class="bi bi-<?php echo $tip['icon']; ?> me-1"></i>
                        <span class="d-none d-sm-inline"><?php echo $tip['title']; ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- General Emergency Preparedness -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h4 class="card-title mb-0">
                <i class="bi bi-list-check me-2"></i>General Emergency Preparedness
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-box me-2 text-primary"></i>Emergency Kit Essentials</h5>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-droplet-fill text-primary me-2"></i>
                            Water (1 gallon per person per day for 3 days)
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-basket-fill text-warning me-2"></i>
                            Non-perishable food (3-day supply)
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-flashlight-fill text-info me-2"></i>
                            Flashlight and extra batteries
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-broadcast text-secondary me-2"></i>
                            Battery-powered or hand crank radio
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-bandaid-fill text-danger me-2"></i>
                            First aid kit and medications
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-phone-fill text-success me-2"></i>
                            Cell phone with chargers and backup battery
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5><i class="bi bi-people-fill me-2 text-primary"></i>Family Emergency Plan</h5>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-telephone-fill text-primary me-2"></i>
                            Identify emergency contact person
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-geo-alt-fill text-warning me-2"></i>
                            Know evacuation routes and meeting places
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-file-text-fill text-info me-2"></i>
                            Keep important documents in waterproof container
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-cash text-success me-2"></i>
                            Have cash in small bills
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                            Know family members' medical information
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-house-door-fill text-secondary me-2"></i>
                            Practice evacuation drills regularly
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Disaster-Specific Safety Tips -->
    <?php foreach ($safety_tips as $key => $tip): ?>
    <div id="<?php echo $key; ?>" class="card shadow-sm mb-4">
        <div class="card-header bg-<?php echo $tip['color']; ?> text-white">
            <h4 class="card-title mb-0">
                <i class="bi bi-<?php echo $tip['icon']; ?> me-2"></i><?php echo $tip['title']; ?>
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="text-success">
                        <i class="bi bi-clock-history me-2"></i>Before
                    </h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($tip['before'] as $item): ?>
                        <li class="list-group-item d-flex align-items-start">
                            <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                            <span><?php echo $item; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="text-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>During
                    </h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($tip['during'] as $item): ?>
                        <li class="list-group-item d-flex align-items-start">
                            <i class="bi bi-arrow-right-circle-fill text-warning me-2 mt-1"></i>
                            <span><?php echo $item; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="text-info">
                        <i class="bi bi-arrow-clockwise me-2"></i>After
                    </h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($tip['after'] as $item): ?>
                        <li class="list-group-item d-flex align-items-start">
                            <i class="bi bi-info-circle-fill text-info me-2 mt-1"></i>
                            <span><?php echo $item; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Emergency Contacts Quick Reference -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            <h4 class="card-title mb-0">
                <i class="bi bi-telephone-fill me-2"></i>Emergency Contacts Quick Reference
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="text-center">
                        <div class="bg-danger text-white rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                            <i class="bi bi-shield-fill fs-4"></i>
                        </div>
                        <h6>MDRRMO</h6>
                        <a href="tel:+639123456789" class="btn btn-danger btn-sm">
                            <i class="bi bi-telephone-fill me-1"></i>Call Now
                        </a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="text-center">
                        <div class="bg-warning text-dark rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                            <i class="bi bi-fire fs-4"></i>
                        </div>
                        <h6>Fire Dept</h6>
                        <a href="tel:+639123456790" class="btn btn-warning btn-sm">
                            <i class="bi bi-telephone-fill me-1"></i>Call Now
                        </a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                        <h6>Police</h6>
                        <a href="tel:+639123456791" class="btn btn-primary btn-sm">
                            <i class="bi bi-telephone-fill me-1"></i>Call Now
                        </a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                            <i class="bi bi-hospital fs-4"></i>
                        </div>
                        <h6>Medical</h6>
                        <a href="tel:+639123456792" class="btn btn-success btn-sm">
                            <i class="bi bi-telephone-fill me-1"></i>Call Now
                        </a>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="contacts.php" class="btn btn-outline-primary">
                    <i class="bi bi-telephone-book me-1"></i>View All Emergency Contacts
                </a>
            </div>
        </div>
    </div>

    <!-- Additional Resources -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="card-title mb-0">
                <i class="bi bi-book me-2"></i>Additional Resources
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Online Resources</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="#" class="text-decoration-none">
                                <i class="bi bi-globe me-2"></i>NDRRMC Official Website
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-decoration-none">
                                <i class="bi bi-cloud-sun me-2"></i>PAGASA Weather Updates
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-decoration-none">
                                <i class="bi bi-geo-alt me-2"></i>Hazard Maps Philippines
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-decoration-none">
                                <i class="bi bi-heart-pulse me-2"></i>Red Cross First Aid Guide
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Mobile Apps</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-phone me-2"></i>NDRRMC Disaster Response App
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-phone me-2"></i>PAGASA Weather App
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-phone me-2"></i>Red Cross Emergency App
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-phone me-2"></i>First Aid by Red Cross
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});


// Highlight current section in navigation
window.addEventListener('scroll', function() {
    const sections = document.querySelectorAll('[id]');
    const navLinks = document.querySelectorAll('a[href^="#"]');
    
    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
            link.classList.add('active');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
