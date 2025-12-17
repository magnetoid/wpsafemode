# Feature Proposal: WP Safe Mode v1.1

## Overview
Based on the code analysis and the goal to "overall improve the app", the following features are proposed. These aim to enhance security, usability, and the capabilities of the AI assistant.

## 1. Security Enhancements (Critical)

### 1.1 AI Security Hardening
**Context**: The `AIController` currently lacks CSRF protection for its POST endpoints (`chat`, `analyze_error_log`, etc.).
- **Proposal**: Implement `CSRFProtection::validate_post_token()` across all `AIController` actions.
- **Benefit**: prevents attackers from triggering expensive AI operations or manipulating context.

### 1.2 Secure Database Backups
**Context**: `SELECT INTO OUTFILE` is used for CSV backups, requiring the dangerous `FILE` privilege.
- **Proposal**: Refactor `backup_tables_csv` to valid PHP-based CSV generation using `fputcsv()`.
- **Benefit**: Removes the need for high-level database privileges, significantly reducing the attack surface.

## 2. AI & Intelligence Features

### 2.1 Automated Security Insights
- **Feature**: Weekly automated scans using the `AIService` to analyze error logs and `wp-config.php` for potential issues.
- **Implementation**: A cron job triggers the analysis, and a summary report is stored/emailed (if email service is active).

### 2.2 Intelligent Plugin Conflict Solver
- **Feature**: Enhance the "Detect Conflicts" AI feature. Instead of just analyzing text, allow it to tentatively "disable" plugins in a simulation mode (using a dry-run flag) to isolate the issue.
- **benefit**: Speeds up debugging for non-technical users.

## 3. System Health & Performance

### 3.1 Database Insight & Query Visualizer
- **Feature**: A new dashboard widget showing Top 10 Largest Tables and "Fragmented Tables".
- **Benefit**: Helps users quickly identify why their database is slow or large.

### 3.2 Server Resource Monitor
- **Feature**: Real-time (AJAX-polled) display of PHP Memory Usage, CPU Load (if available), and Disk Space.
- **Benefit**: "Safe Mode" is often used when sites are crashing; knowing resource limits is crucial.

## 4. UI/UX Improvements

### 4.1 Modern Dark Mode
- **Feature**: A toggle to switch the entire dashboard to a "Dark Mode" theme.
- **Implementation**: CSS variables for colors, saved in local storage or user preferences.
- **Benefit**: Reduced eye strain, modern feel.

### 4.2 Interactive File Manager
- **Feature**: Upgrade the listing view to a proper File Manager with "Edit", "Delete", and "Permissions" (chmod) context menus.
- **Benefit**: Easier file operations without needing distinct "actions".

## Recommendation for Next Steps

We should prioritize:
1.  **Security Fixes** (AI CSRF & Backup refactor) - Essential foundation.
2.  **UI/UX (Dark Mode)** - High visual impact ("wow factor").
3.  **One Major Feature**: Either "Automated Security Insights" or "Database Insight".

**Please select which features you would like to proceed with.**
