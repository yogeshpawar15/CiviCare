<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frequently Asked Questions - CiviCare</title>
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
            max-width: 1000px;
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
            max-width: 900px;
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

        .faq-category {
            margin-bottom: 3rem;
        }

        .category-title {
            font-size: 1.5rem;
            color: #1e3c72;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #ff6b35;
        }

        .category-title i {
            font-size: 1.8rem;
            color: #ff6b35;
        }

        .faq-item {
            background: white;
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }

        .faq-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            transition: all 0.3s;
            font-weight: 600;
            color: #1e3c72;
            user-select: none;
        }

        .faq-question:hover {
            background: #e8ecf1;
        }

        .faq-question.active {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .faq-icon {
            font-size: 1.3rem;
            transition: transform 0.3s;
        }

        .faq-question.active .faq-icon {
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }

        .faq-answer.active {
            max-height: 500px;
            padding: 1.5rem;
            padding-top: 0;
        }

        .faq-answer p {
            color: #555;
            line-height: 1.8;
            font-size: 1rem;
            font-weight: 400;
        }

        .faq-answer ul {
            margin-left: 1.5rem;
            color: #555;
            line-height: 1.8;
        }

        .faq-answer li {
            margin-bottom: 0.5rem;
        }

        .search-box {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .search-box input {
            width: 100%;
            padding: 0.8rem 1.2rem;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            color: #999;
            font-size: 1.1rem;
        }

        footer {
            background: #1a1a1a;
            color: white;
            padding: 2rem 0 1rem;
            margin-top: 4rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header-content h1 {
                font-size: 1.8rem;
            }

            .container {
                padding: 0 1rem 2rem;
            }

            .faq-question {
                padding: 1rem;
                font-size: 0.95rem;
            }

            .faq-answer {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <h1><i class="fas fa-question-circle"></i> Frequently Asked Questions</h1>
            <p>Find answers to common questions about CiviCare</p>
        </div>
    </header>

    <div class="container">
        <div class="nav-back">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- SEARCH BOX -->
        <div class="search-box">
            <input type="text" id="faqSearch" placeholder="Search FAQs... (e.g., 'How to file complaint', 'tracking')" onkeyup="searchFAQ()">
        </div>

        <!-- GENERAL QUESTIONS CATEGORY -->
        <div class="faq-category">
            <div class="category-title">
                <i class="fas fa-info-circle"></i>
                General Questions
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What is CiviCare?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>CiviCare is an official municipal complaint management system designed to bridge the gap between citizens and the government. It allows citizens to file complaints about municipal services, track their status in real-time, and receive timely resolutions. The platform ensures transparency, accountability, and efficient service delivery.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Is CiviCare free to use?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, CiviCare is completely free to use. There are no charges for filing complaints, tracking status, or accessing any of our services. It is a citizen-friendly platform provided by the municipal corporation.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How do I register on CiviCare?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Registration is simple and straightforward:</p>
                    <ul>
                        <li>Click on 'Citizen Login' in the top menu</li>
                        <li>Click on 'Register' to create a new account</li>
                        <li>Fill in your details: Name, Email, Phone, Address</li>
                        <li>Create a secure password</li>
                        <li>Verify your email to activate your account</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Do I need to register to file a complaint?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, you need to have a registered account to file a complaint. This helps us verify your identity and maintain a record of all complaints for transparency and accountability purposes. Registration only takes a few minutes.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What are the different departments in CiviCare?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>CiviCare covers the following municipal departments:</p>
                    <ul>
                        <li>Water Department - Water supply, drainage, pipeline issues</li>
                        <li>Electricity Department - Power supply, street lights, electrical faults</li>
                        <li>Road Department - Potholes, road maintenance, construction</li>
                        <li>Sanitation Department - Garbage collection, street cleaning, waste</li>
                        <li>Public Works Department - Public buildings, infrastructure maintenance</li>
                        <li>Parks Department - Parks, gardens, green spaces maintenance</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FILING COMPLAINTS CATEGORY -->
        <div class="faq-category">
            <div class="category-title">
                <i class="fas fa-briefcase"></i>
                Filing Complaints
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How do I file a complaint?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Follow these steps to file a complaint:</p>
                    <ul>
                        <li>Log in to your CiviCare account</li>
                        <li>Click on 'File Complaint' from the Services menu</li>
                        <li>Select the relevant department</li>
                        <li>Provide detailed information about the issue</li>
                        <li>Add location and landmark details</li>
                        <li>Upload photos (optional but recommended)</li>
                        <li>Set priority level and submit</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What information should I include in my complaint?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>To ensure your complaint is processed quickly, please include:</p>
                    <ul>
                        <li>Clear and concise title of the complaint</li>
                        <li>Detailed description of the issue</li>
                        <li>Exact location and prominent landmarks</li>
                        <li>Date and time when you first noticed the problem</li>
                        <li>Photographs or videos (optional)</li>
                        <li>Priority level assessment</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Can I upload photos with my complaint?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, you can upload photos and documents with your complaint. Attaching clear photos of the issue can significantly speed up the resolution process. Supported formats include JPG, PNG, PDF, and other common image formats.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What does priority level mean?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Priority levels help us address urgent issues faster:</p>
                    <ul>
                        <li><strong>Low:</strong> Minor issues that don't affect daily life (e.g., minor road damage)</li>
                        <li><strong>Medium:</strong> Issues affecting services (e.g., water supply disruption)</li>
                        <li><strong>High:</strong> Urgent issues affecting safety or health (e.g., no drinking water)</li>
                        <li><strong>Critical:</strong> Emergency situations requiring immediate action</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Will my personal information be kept confidential?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, your personal information is completely confidential and protected. We use enterprise-grade encryption to secure all data. Your contact details are only shared with the relevant department staff handling your complaint and are never shared with third parties.</p>
                </div>
            </div>
        </div>

        <!-- TRACKING COMPLAINTS CATEGORY -->
        <div class="faq-category">
            <div class="category-title">
                <i class="fas fa-search"></i>
                Tracking Complaints
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How do I track my complaint status?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>You can track your complaint status anytime:</p>
                    <ul>
                        <li>Log in to your CiviCare account</li>
                        <li>Click on 'Track Complaint' from the Services menu</li>
                        <li>Your complaints will be listed with their current status</li>
                        <li>Click on any complaint to view detailed status updates</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What do the different complaint statuses mean?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Complaint statuses indicate the progress of your complaint:</p>
                    <ul>
                        <li><strong>Pending Validation:</strong> Under review for verification</li>
                        <li><strong>Validated:</strong> Approved and forwarded to department</li>
                        <li><strong>Assigned to Staff:</strong> Assigned to responsible staff member</li>
                        <li><strong>In Progress:</strong> Staff is working on resolution</li>
                        <li><strong>Resolved:</strong> Work completed, awaiting your feedback</li>
                        <li><strong>Closed:</strong> Complaint completed</li>
                        <li><strong>Rejected:</strong> Complaint could not be processed</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>How often are complaints updated?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Complaint status is updated regularly as the assigned staff works on resolution. You will receive email and SMS notifications whenever your complaint status changes. For urgent complaints, updates are provided more frequently.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Can I view the history of my complaints?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, you can view the complete history of all your complaints. Click on 'View History' in the Services menu to see all complaints you have filed, including resolved and closed complaints.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Will I receive notifications about my complaint?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, you will receive automatic notifications via email and SMS whenever:</p>
                    <ul>
                        <li>Your complaint is successfully filed</li>
                        <li>Your complaint status changes</li>
                        <li>Staff is assigned to your complaint</li>
                        <li>Your complaint is resolved</li>
                        <li>The department needs additional information</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FEEDBACK & RESOLUTION CATEGORY -->
        <div class="faq-category">
            <div class="category-title">
                <i class="fas fa-star"></i>
                Feedback & Resolution
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Can I provide feedback on the resolution?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, after your complaint is resolved, you can rate the work done and provide feedback. Your feedback helps us improve our services and hold departments accountable. You can rate from 1 to 5 stars and add comments about your experience.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What is the typical resolution timeframe?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Resolution timeframes vary by complaint type and priority:</p>
                    <ul>
                        <li><strong>Critical:</strong> 24-48 hours</li>
                        <li><strong>High Priority:</strong> 3-5 days</li>
                        <li><strong>Medium Priority:</strong> 7-10 days</li>
                        <li><strong>Low Priority:</strong> 15-20 days</li>
                    </ul>
                    <p>You will receive regular updates if additional time is needed.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Can I reopen a closed complaint?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>If you feel the complaint was not satisfactorily resolved, you can contact our support team through the contact form or raise a new related complaint with additional details. We can escalate your concern to senior management if needed.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>What if my complaint is rejected?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>If your complaint is rejected, you will receive a notification with the reason. Common reasons for rejection include:</p>
                    <ul>
                        <li>Complaint is outside municipal jurisdiction</li>
                        <li>Already resolved complaint</li>
                        <li>Insufficient information provided</li>
                        <li>Inappropriate content</li>
                    </ul>
                    <p>You can contact support for clarification and file a new complaint with revised information.</p>
                </div>
            </div>
        </div>

        <!-- TECHNICAL SUPPORT CATEGORY -->
        <div class="faq-category">
            <div class="category-title">
                <i class="fas fa-headset"></i>
                Technical Support
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>I forgot my password. How do I reset it?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>To reset your password:</p>
                    <ul>
                        <li>Click 'Forgot Password' on the login page</li>
                        <li>Enter your registered email address</li>
                        <li>You will receive a password reset link via email</li>
                        <li>Click the link and create a new password</li>
                        <li>Log in with your new password</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Is CiviCare available on mobile?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, CiviCare is fully responsive and works on all mobile devices. You can access it through any web browser on your smartphone. The platform is optimized for mobile use with touch-friendly buttons and easy navigation.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Which browsers are supported?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>CiviCare works on all modern web browsers including:</p>
                    <ul>
                        <li>Google Chrome (latest version)</li>
                        <li>Mozilla Firefox (latest version)</li>
                        <li>Safari (latest version)</li>
                        <li>Microsoft Edge (latest version)</li>
                        <li>Mobile browsers (Chrome, Safari, Firefox)</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Is my data secure on CiviCare?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, CiviCare uses enterprise-grade security measures:</p>
                    <ul>
                        <li>SSL/TLS encryption for data transmission</li>
                        <li>Secure password hashing</li>
                        <li>Regular security audits</li>
                        <li>Privacy compliance with all regulations</li>
                        <li>Firewall protection</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <span>Who can I contact for technical support?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <p>For technical support, you can:</p>
                    <ul>
                        <li>Visit our <a href="contact.php" style="color: #ff6b35;">Contact page</a> to send us a message</li>
                        <li>Email us at support@civicare.gov.in</li>
                        <li>Call our helpline at +(91) 2345-678900</li>
                        <li>Helpline is available Monday-Saturday, 10 AM - 6 PM</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 CiviCare - Municipal Services. All rights reserved.</p>
    </footer>

    <script>
        function toggleFAQ(element) {
            const question = element;
            const answer = question.nextElementSibling;

            // Close all other FAQs
            document.querySelectorAll('.faq-answer.active').forEach(activeAnswer => {
                if (activeAnswer !== answer) {
                    activeAnswer.classList.remove('active');
                    activeAnswer.previousElementSibling.classList.remove('active');
                }
            });

            // Toggle current FAQ
            question.classList.toggle('active');
            answer.classList.toggle('active');
        }

        function searchFAQ() {
            const searchInput = document.getElementById('faqSearch').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            let visibleCount = 0;

            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();

                if (question.includes(searchInput) || answer.includes(searchInput)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Show no results message
            const noResults = document.querySelector('.no-results');
            if (noResults) noResults.remove();

            if (visibleCount === 0) {
                const container = document.querySelector('.container');
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results';
                noResultsDiv.textContent = 'No FAQs found matching your search. Please try different keywords.';
                container.appendChild(noResultsDiv);
            }
        }
    </script>
</body>
</html>
