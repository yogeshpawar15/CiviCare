<?php
session_start();
require 'config/database.php';

$conn = getDatabaseConnection();

// Fetch all active departments
$sql = "SELECT * FROM departments WHERE is_active = 1 ORDER BY id";
$result = $conn->query($sql);
$departments = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Department information with details
$deptDetails = [
    1 => [
        'formed' => '1985',
        'works' => 'The Water Department manages the municipal water supply system including pipelines, water treatment, distribution, and drainage systems. They handle complaints regarding water quality, supply interruptions, and drainage issues.',
        'achievements' => 'Providing clean water to 500,000+ citizens | 95% citywide coverage | 24/7 emergency response'
    ],
    2 => [
        'formed' => '1990',
        'works' => 'The Electricity Department manages municipal power supply, electrical infrastructure, street lighting, and public services electricity. They address power outages, electrical faults, street light maintenance, and billing issues.',
        'achievements' => 'Uninterrupted power supply in 98 wards | 15,000+ street lights maintained | Average response time: 2 hours'
    ],
    3 => [
        'formed' => '1988',
        'works' => 'The Road Department maintains and constructs municipal roads and highways. They handle pothole repairs, road construction, traffic management, and infrastructure improvements across the city.',
        'achievements' => 'Maintained 1,200 km roads | 50+ new roads constructed | Zero-fatality safety initiative'
    ],
    4 => [
        'formed' => '1992',
        'works' => 'The Sanitation Department manages garbage collection, street cleaning, waste management, and environmental hygiene. They work to maintain cleanliness across all city areas and handle waste disposal efficiently.',
        'achievements' => '100% waste collection coverage | 98% cleanliness standards maintained | 500+ sanitation workers'
    ],
    5 => [
        'formed' => '1987',
        'works' => 'The Public Works Department handles maintenance of public buildings, community centers, municipal offices, and public infrastructure. They ensure all government facilities are well-maintained and accessible.',
        'achievements' => 'Maintained 200+ public buildings | 50+ community centers operational | Quick maintenance response'
    ],
    6 => [
        'formed' => '1995',
        'works' => 'The Parks Department manages municipal parks, gardens, green spaces, and recreational areas. They handle landscaping, maintenance of public gardens, tree plantation programs, and public space development.',
        'achievements' => 'Maintained 50+ parks and gardens | 100,000+ trees planted | Green city initiative'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Departments - CiviCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 3rem;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .header-content h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header-content p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem 4rem;
        }

        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .dept-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .dept-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.2);
        }

        .dept-card-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .dept-card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .dept-card-header h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .dept-card-body {
            padding: 1.5rem;
        }

        .dept-card-body p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .view-details {
            display: inline-block;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 600;
        }

        .view-details:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            transform: translateX(5px);
        }

        footer {
            background: #1a1a1a;
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
            text-align: center;
        }

        .nav-back {
            margin-bottom: 2rem;
        }

        .nav-back a {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .nav-back a:hover {
            color: #ff6b35;
        }

        @media (max-width: 768px) {
            .departments-grid {
                grid-template-columns: 1fr;
            }

            .header-content h1 {
                font-size: 1.8rem;
            }

            .container {
                padding: 0 1rem 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <h1><i class="fas fa-building"></i> Our Departments</h1>
            <p>Learn about the various municipal departments serving our city</p>
        </div>
    </header>

    <div class="container">
        <div class="nav-back">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="departments-grid">
            <?php foreach ($departments as $dept): ?>
                <div class="dept-card">
                    <div class="dept-card-header">
                        <div class="dept-card-icon">
                            <?php
                                $icons = [
                                    1 => 'fa-tint',
                                    2 => 'fa-bolt',
                                    3 => 'fa-road',
                                    4 => 'fa-trash',
                                    5 => 'fa-tools',
                                    6 => 'fa-leaf'
                                ];
                                echo '<i class="fas ' . $icons[$dept['id']] . '"></i>';
                            ?>
                        </div>
                        <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                    </div>
                    <div class="dept-card-body">
                        <p><?php echo htmlspecialchars(substr($dept['description'], 0, 120)) . '...'; ?></p>
                        <a href="department-info.php?id=<?php echo $dept['id']; ?>" class="view-details">
                            View Details <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 CiviCare - Municipal Services. All rights reserved.</p>
    </footer>
</body>
</html>
