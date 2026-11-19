<?php
session_start();
require 'config/database.php';

$conn = getDatabaseConnection();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $subject = $conn->real_escape_string($_POST['subject'] ?? '');
    $message_text = $conn->real_escape_string($_POST['message'] ?? '');
    $category = $conn->real_escape_string($_POST['category'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($subject) || empty($message_text)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($phone) < 10) {
        $error = 'Please enter a valid phone number';
    } else {
        // Save contact message to database
        $sql = "INSERT INTO contact_messages (name, email, phone, subject, category, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssssss", $name, $email, $phone, $subject, $category, $message_text);
            if ($stmt->execute()) {
                // Send confirmation email
                $headers = "From: noreply@civicare.gov.in\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                
                $email_subject = "[CiviCare] We received your message";
                $email_body = "<html><body>";
                $email_body .= "<p>Dear " . htmlspecialchars($name) . ",</p>";
                $email_body .= "<p>Thank you for contacting CiviCare. We have received your message and will respond within 24 hours.</p>";
                $email_body .= "<p><strong>Your Reference ID:</strong> #" . time() . "</p>";
                $email_body .= "<p>Best regards,<br>CiviCare Support Team</p>";
                $email_body .= "</body></html>";
                
                @mail($email, $email_subject, $email_body, $headers);
                
                $message = 'Thank you for your message! We will respond to you within 24 hours.';
            } else {
                $error = 'Error submitting your message. Please try again.';
            }
        }
    }
}

// Check if contact_messages table exists, if not create it
$check_table = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($check_table->num_rows === 0) {
    $create_table = "CREATE TABLE contact_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        subject VARCHAR(200),
        category VARCHAR(50),
        message LONGTEXT,
        status VARCHAR(20),
        created_at TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_table);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - CiviCare</title>
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
            padding: 3rem 0;
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .contact-form, .contact-info {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .contact-form h2, .contact-info h2 {
            font-size: 1.5rem;
            color: #1e3c72;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .contact-form h2 i, .contact-info h2 i {
            color: #ff6b35;
            font-size: 1.8rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1e3c72;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff6b35 0%, #ff8555 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 107, 53, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.8rem;
            align-items: flex-start;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .contact-section {
            margin-bottom: 2rem;
        }

        .contact-section h3 {
            font-size: 1.1rem;
            color: #1e3c72;
            margin-bottom: 1rem;
        }

        .contact-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .contact-details {
            flex-grow: 1;
        }

        .contact-details p {
            margin: 0.3rem 0;
            line-height: 1.6;
            color: #555;
        }

        .contact-details strong {
            display: block;
            color: #1e3c72;
            margin-bottom: 0.3rem;
        }

        .contact-details a {
            color: #ff6b35;
            text-decoration: none;
            transition: all 0.3s;
        }

        .contact-details a:hover {
            color: #1e3c72;
        }

        .contact-details .small {
            font-size: 0.9rem;
            color: #666;
        }

        footer {
            background: #1a1a1a;
            color: white;
            padding: 2rem 0 1rem;
            margin-top: 4rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .header-content h1 {
                font-size: 1.8rem;
            }

            .contact-form, .contact-info {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <h1><i class="fas fa-envelope"></i> Contact Us</h1>
            <p>Get in touch with our support team</p>
        </div>
    </header>

    <div class="container">
        <div class="nav-back">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="content-grid">
            <!-- CONTACT FORM -->
            <div class="contact-form">
                <h2><i class="fas fa-paper-plane"></i> Send Message</h2>

                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select a category</option>
                            <option value="complaint_info">Complaint Information</option>
                            <option value="technical_support">Technical Support</option>
                            <option value="feature_request">Feature Request</option>
                            <option value="feedback">General Feedback</option>
                            <option value="complaint">Escalated Complaint</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>

                    <button type="submit" class="submit-btn">Send Message</button>
                </form>
            </div>

            <!-- CONTACT INFORMATION -->
            <div class="contact-info">
                <h2><i class="fas fa-info-circle"></i> Contact Information</h2>

                <div class="contact-section">
                    <h3>Main Office</h3>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-details">
                            <strong>CiviCare Main Office</strong>
                            <p>Three Archives Building</p>
                            <p>Pandit Colony, Nashik</p>
                            <p>Maharashtra - 422002</p>
                        </div>
                    </div>
                </div>

                <div class="contact-section">
                    <h3>Contact Numbers</h3>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-details">
                            <strong>Helpline</strong>
                            <p><a href="tel:+919876543210">+(91) 9876543210</a></p>
                            <p class="small">Monday - Saturday: 10:00 AM - 6:00 PM</p>
                        </div>
                    </div>
                </div>

                <div class="contact-section">
                    <h3>Email</h3>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-details">
                            <strong>General Inquiries</strong>
                            <p><a href="mailto:info@civicare.gov.in">info@civicare.gov.in</a></p>
                            <strong style="margin-top: 1rem">Support</strong>
                            <p><a href="mailto:support@civicare.gov.in">support@civicare.gov.in</a></p>
                        </div>
                    </div>
                </div>

                <div class="contact-section">
                    <h3>Follow Us</h3>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <div class="contact-details">
                            <strong>Social Media</strong>
                            <p>
                                <a href="#"><i class="fab fa-facebook-f"></i> Facebook</a> | 
                                <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                            </p>
                            <p>
                                <a href="#"><i class="fab fa-instagram"></i> Instagram</a> | 
                                <a href="#"><i class="fab fa-youtube"></i> YouTube</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 CiviCare - Municipal Services. All rights reserved.</p>
    </footer>
</body>
</html>