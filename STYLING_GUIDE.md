# CiviCare Admin & Staff Styling Guide

## Overview

The modern theme has been standardized across all admin and staff portal files. A shared CSS stylesheet has been created to maintain consistent styling and branding throughout the application.

## Quick Implementation Guide

### For All Admin & Staff Files

To apply the modern theme to any PHP file (admin or staff), follow these steps:

#### 1. **Include the Shared Stylesheet**

Add this line to the `<head>` section of your PHP files:

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/modern-theme.css">
```

#### 2. **Use Standard HTML Structure**

Replace your existing header with:

```php
<header class="main-header">
    <div class="header-content">
        <div class="logo-section">
            <a href="index.php" class="logo">
                <i class="fas fa-city"></i>
            </a>
            <div class="logo-text">
                <h1>CIVICARE</h1>
                <p><?php echo $page_type; ?> Portal</p>
            </div>
        </div>

        <nav class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="complaints.php">Complaints</a>
            <a href="reports.php">Reports</a>
            <!-- Add more nav items as needed -->
            <a href="../logout.php" class="logout-btn">Logout</a>
        </nav>
    </div>
</header>
```

#### 3. **Standard Page Layout**

Use this container structure for consistency:

```php
<div class="container">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Page Title <?php if ($role): ?><span class="role-badge"><?php echo $role; ?></span><?php endif; ?></h1>
        <p>Page description goes here</p>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="filter_name">Filter Name</label>
                    <select id="filter_name" name="filter_name">
                        <option value="">All</option>
                        <!-- Options -->
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='this_file.php'"><i class="fas fa-redo"></i> Reset</button>
            </div>
        </form>
    </div>

    <!-- Main Content (Tables, Cards, etc.) -->
    <div class="table-section">
        <div class="table-header">
            <h2><i class="fas fa-list"></i> Data List</h2>
            <span class="table-info"><strong><?php echo $total; ?></strong> items found</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Column 1</th>
                    <th>Column 2</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Table rows -->
            </tbody>
        </table>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h4>About CiviCare</h4>
            <p>Digital platform for municipal complaint management.</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> CiviCare. All rights reserved.</p>
    </div>
</footer>
```

## CSS Classes Reference

### Colors & State Classes

| Class | Usage | Color |
|-------|-------|-------|
| `.status-pending` | Pending complaints | #fff3cd |
| `.status-assigned` | Assigned items | #d1ecf1 |
| `.status-in_progress` | In progress | #cce5ff |
| `.status-resolved` | Completed/Resolved | #d4edda |
| `.status-escalated` | Escalated | #f8d7da |
| `.priority-low` | Low priority | #d4edda |
| `.priority-high` | High priority | #fff3cd |
| `.priority-urgent` | Urgent priority | #f8d7da |

### Component Classes

| Class | Purpose |
|-------|---------|
| `.stats-grid` | Grid for stat cards |
| `.stat-card` | Individual stat display |
| `.filters-section` | Filters container |
| `.filters-grid` | Grid layout for filters |
| `.table-section` | Table container |
| `.action-buttons` | Action button group |
| `.action-btn-*` | Individual action buttons |
| `.pagination` | Pagination controls |

## Files Recently Updated

### ✅ Completed
- `admin/complaints.php` - Updated header and styling
- `assets/css/modern-theme.css` - Created shared stylesheet

### 📝 Ready for Update (Use the guide above)
- `admin/reports.php`
- `admin/settings.php`
- `admin/track_complaint.php`
- `admin/index.php`
- `admin/departments.php`
- `admin/staff.php`
- `admin/users.php`

## Key Features of the Modern Theme

✨ **Modern Design**
- Gradient blue header with orange accents
- Clean, spacious layout
- Consistent shadows and spacing

🎨 **Color Palette**
```
Primary: #1e3c72, #2a5298 (Blue gradient)
Accent: #ff6b35 (Orange)
Background: #f8f9fa (Light gray)
```

📱 **Responsive**
- Mobile-friendly breakpoints
- Flexible grid layouts
- Touch-friendly buttons

⚡ **Performance**
- CSS variables for easy customization
- Minimal animation (0.3s transitions)
- Optimized for all browsers

## Customization

To customize colors globally, edit `assets/css/modern-theme.css` and modify the CSS variables at the top:

```css
:root {
    --primary-color: #1e3c72;
    --accent-color: #ff6b35;
    --background: #f8f9fa;
    /* etc... */
}
```

## Browser Support

- Chrome/Edge (Latest)
- Firefox (Latest)
- Safari (Latest)
- Mobile browsers

## Notes

- All functionality remains unchanged - only styling is updated
- Database queries and form submissions work exactly as before
- Font Awesome icons are included via CDN
- Shared CSS can be cached by browsers for better performance

---

**Last Updated:** March 1, 2026
**Version:** 1.0 - Modern Theme Release
