# YWB.CommentsCleanNReply - Project Documentation

## Product Overview

**Instant FB Comments Clean'N'Reply** is an automated Facebook comment moderation system designed for managing Facebook page comments in real-time. The system listens to Facebook webhooks and automatically processes incoming comments based on configurable rules.

### Purpose
- **Automated Comment Moderation**: Automatically hide or delete unwanted comments
- **Smart Auto-Reply System**: Respond to comments containing specific trigger words with predefined messages (with optional images)
- **Content Management**: Bulk delete/clean page content (posts, photos, videos)
- **Multi-Page Management**: Handle multiple Facebook pages from a single admin panel

### Core Know-How
1. **Webhook-Based**: Uses Facebook Graph API webhooks to receive real-time comment notifications
2. **Rule-Based Processing**: Matches comments against trigger word rules to determine actions
3. **Dual Mode Operation**: Can either hide comments (reversible) or permanently delete them
4. **Token Management**: Stores page access tokens and app tokens for API access
5. **Per-Page Configuration**: Each page can have its own settings, rules, and cleaner status

### Workflow
1. Facebook sends webhook notification when a comment is added
2. System checks if the page has cleaner enabled
3. If enabled, checks comment against reply rules
4. If a rule matches → replies with predefined text/image
5. If no rule matches → hides or deletes comment based on page mode
6. All actions are logged for monitoring

---

## Features

### 1. Facebook App Management
**Location**: `admin_apps.php`

- **Add Facebook Apps**: Input app access token to automatically fetch and register apps
- **Auto-Subscribe**: Automatically subscribes apps to page webhooks upon addition
- **Subscription Monitoring**: Displays current webhook subscription status (active/inactive/not subscribed)
- **Token Display**: Shows truncated tokens with copy-to-clipboard functionality
- **Unsubscribe & Remove**: Clean removal with automatic webhook unsubscription

**Database**: `apps` table stores `app_id`, `app_name`, `app_token`

### 2. Facebook Page Management
**Location**: `admin_pages.php`

- **Fetch User Pages**: Use user access token to retrieve all managed pages
- **Batch Page Addition**: Select multiple pages to add at once
- **Auto-Subscribe to Feed**: Automatically subscribes pages to feed webhooks
- **Token Validation**: Real-time validation of page access tokens with expiry dates
- **Page Information Display**: Shows page avatar, name, and ID
- **Token Management**: Copy tokens to clipboard, view expiration dates
- **Clean Removal**: Unsubscribes from webhooks when removing pages

**Database**: `fan_pages` table stores `id`, `access_token`, `page_name`, `page_avatar`, `delete_mode`, `cleaner_enabled`

### 3. Comment Cleaner
**Location**: `admin_cleaner.php`

- **Enable/Disable Cleaner**: Toggle comment processing per page with a switch
- **Processing Mode Selection**: Choose between "Hide" or "Delete" mode per page
- **Disabled Mode Safety**: Processing mode selector is disabled when cleaner is off
- **Bulk Content Cleaning**: One-click deletion of all posts, photos, and videos from a page
- **Cleaning Results**: Detailed statistics on deleted posts, photos, and videos
- **Error Reporting**: Logs and displays errors encountered during cleaning

**Working Modes**:
- **Hide Mode** (`delete_mode = 0`): Comments are hidden but remain in Facebook's system
- **Delete Mode** (`delete_mode = 1`): Comments are permanently deleted

### 4. Reply Rules Management
**Location**: `admin_replies.php`

- **Add Reply Rules**: Create rules with trigger words, reply text, and optional images
- **Edit Rules**: Modify existing rules via modal dialog
- **Image Attachments**: Upload JPG, PNG, or GIF images to attach to replies
- **Image Management**: Replace or remove images from replies
- **Trigger Word Matching**: Comma-separated keywords, supports wildcard `*` to match all comments
- **Case-Insensitive Matching**: Keywords match regardless of case
- **Per-Page Rules**: Rules are associated with specific pages
- **Rule Display**: Shows all rules with page info, trigger words, and preview

**Trigger Word Logic**:
- Keywords are comma-separated (e.g., "price,cost,how much")
- Wildcard `*` matches all comments
- Matching uses `stripos()` for case-insensitive substring search
- First matching rule wins (stops processing further rules)

**Database**: `reply_rules` table stores `rule_id`, `page_id`, `trigger_words`, `reply_text`, `image_path`

### 5. Webhook Handler
**Location**: `hookhandler.php`, `index.php`

- **Webhook Verification**: Handles Facebook's webhook challenge verification
- **Comment Processing**: Processes incoming comment webhooks in real-time
- **Self-Comment Detection**: Ignores comments from the page itself
- **Cleaner Status Check**: Only processes comments if cleaner is enabled for the page
- **Rule Matching Engine**: Iterates through reply rules to find matches
- **Comment Actions**: Replies to matched comments, hides/deletes unmatched ones
- **Error Handling**: Graceful error handling with logging

**Webhook URL**: `https://yourdomain.com/index.php`

### 6. Logging System
**Location**: `logger.php`, `logviewer.php`

- **Daily Log Files**: Separate log file for each day (YYYY-MM-DD.log)
- **Log Levels**: Trace, Info, Warning, Error with color coding
- **Debug Mode**: Displays logs on-screen when debug mode is enabled
- **Log Viewer UI**: Web interface to view logs by date range
- **Level Filtering**: Filter logs by level in the viewer
- **Automatic Directory Creation**: Creates logs directory if missing

**Log Levels**:
- **Trace**: Detailed debugging information (keyword checks, etc.)
- **Info**: General information (comment received, action taken)
- **Warning**: Non-critical issues
- **Error**: Critical errors that require attention

### 7. Authentication System
**Location**: `login.php`, `logout.php`

- **Simple Password Authentication**: Single password for admin access
- **Session Management**: Uses PHP sessions to maintain login state
- **Auto-redirect**: Redirects to admin panel after successful login
- **Secure Logout**: Proper session cleanup on logout
- **Access Protection**: All admin pages check for valid session

**Default Password**: `qwerty` (configurable in `settings.php`)

### 8. Database Management
**Location**: `db.php`

- **SQLite3 Database**: Lightweight file-based database (`comments.db`)
- **Automatic Schema Creation**: Creates tables if they don't exist
- **Schema Migration**: Adds missing columns to existing databases
- **Foreign Key Support**: Enforces referential integrity
- **Prepared Statements**: Uses parameterized queries for security
- **Error Logging**: All database errors are logged

**Tables**:
1. **fan_pages**: Stores page information and settings
2. **reply_rules**: Stores auto-reply rules (cascading delete on page removal)
3. **apps**: Stores Facebook app information

### 9. Facebook API Integration
**Location**: `facebook.php`

- **Graph API Wrapper**: Clean abstraction over Facebook Graph API v22.0
- **Token Validation**: Validates tokens and retrieves expiration info
- **Page Operations**: Subscribe/unsubscribe pages, get page info
- **Comment Operations**: Hide, delete, reply to comments
- **Image Upload**: Upload and attach images to comment replies
- **App Operations**: Subscribe/unsubscribe apps, get subscriptions
- **Bulk Content Deletion**: Delete all posts, photos, and videos with pagination
- **Error Handling**: Comprehensive error checking with detailed logging
- **SSL Configuration**: Includes SSL verification settings

**API Version**: Facebook Graph API v22.0

---

## Conventions

### Coding Style

#### PHP Style
- **Class Names**: PascalCase (e.g., `CommentsDatabase`, `FacebookAPI`, `FBWebhookHandler`)
- **Method Names**: camelCase (e.g., `getFanPages()`, `addReplyRule()`, `hide_comment()`)
- **Variable Names**: snake_case for local variables (e.g., `$page_id`, `$access_token`)
- **Constants**: UPPER_SNAKE_CASE for class constants (e.g., `Settings::$dbFilePath`)
- **File Organization**: One class per file, class name matches filename
- **Error Handling**: Try-catch blocks with logging, graceful degradation

#### Database Conventions
- **Table Names**: snake_case plural (e.g., `fan_pages`, `reply_rules`, `apps`)
- **Column Names**: snake_case (e.g., `page_id`, `access_token`, `trigger_words`)
- **Primary Keys**: Either `id` or `{table_name}_id` (e.g., `rule_id`, `app_id`)
- **Foreign Keys**: Named after referenced table (e.g., `page_id` references `fan_pages.id`)
- **Boolean Fields**: INTEGER (0/1) for SQLite compatibility

#### HTML/UI Conventions
- **Bootstrap 5**: Consistent use of Bootstrap classes
- **Icons**: Bootstrap Icons for all UI icons
- **Responsive Design**: Mobile-friendly layouts with responsive tables
- **Forms**: POST with hidden action fields for form routing
- **Security**: `htmlspecialchars()` on all user-facing output
- **Navigation**: Sidebar navigation with expand/collapse functionality

### Architecture Patterns

#### MVC-Like Separation
- **Models**: Database classes (e.g., `CommentsDatabase`)
- **Controllers**: Admin pages handle POST/GET logic
- **Views**: HTML embedded in admin pages with PHP templating

#### Include Pattern
```php
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db.php';
```

#### Session Management Pattern
```php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
```

#### Form Processing Pattern
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_name'])) {
        // Process action
        // Set $message or $error
        header('Location: admin.php?page=x&msg=...&err=...');
        exit;
    }
}
```

#### Logging Pattern
```php
CommentsLogger::log("Message here", 'Level', $die_on_error);
```

### File Organization

```
project-root/
├── admin.php              # Main admin panel (router)
├── admin_apps.php         # Apps management page
├── admin_pages.php        # Pages management page
├── admin_cleaner.php      # Cleaner settings page
├── admin_replies.php      # Reply rules page
├── login.php              # Login page
├── logout.php             # Logout handler
├── index.php              # Webhook entry point
├── hookhandler.php        # Webhook processing logic
├── db.php                 # Database class
├── facebook.php           # Facebook API class
├── logger.php             # Logging class
├── logviewer.php          # Log viewer UI
├── settings.php           # Configuration
├── comments.db            # SQLite database
├── logs/                  # Daily log files
├── uploads/               # Uploaded images
└── .gitignore
```

### Security Practices

1. **SQL Injection Prevention**: All queries use prepared statements with parameter binding
2. **XSS Prevention**: All output uses `htmlspecialchars()`
3. **CSRF Protection**: Form actions verified via POST method and hidden fields
4. **Session Security**: Session-based authentication with proper cleanup
5. **Token Security**: Tokens stored in database, truncated in UI
6. **File Upload Validation**: Type and extension checking for uploads
7. **Error Hiding**: Production mode hides error details from users

---

## Technical Details

### Languages
- **PHP**: Primary backend language (version 7.x+ recommended)
- **SQL**: SQLite3 dialect
- **JavaScript**: Client-side interactions (minimal)
- **HTML5**: Markup
- **CSS3**: Styling (Bootstrap framework)

### Libraries & Frameworks

#### Backend
- **SQLite3**: PHP extension for SQLite database
- **cURL**: PHP extension for HTTP requests to Facebook API
- **JSON**: PHP json_encode/decode for API communication
- **Sessions**: PHP session management
- **File Upload**: PHP native file handling

#### Frontend
- **Bootstrap 5.1.3**: UI framework (CDN)
  - Grid system for responsive layouts
  - Form controls and validation
  - Cards and tables
  - Alerts and badges
  - Modal dialogs
- **Bootstrap Icons 1.7.2**: Icon library (CDN)
  - Sidebar icons
  - Button icons
  - Status indicators

#### Third-Party APIs
- **Facebook Graph API v22.0**: Social media integration
  - Webhook subscriptions
  - Comment management
  - Page and app management
  - Token validation

### Database Schema

#### fan_pages
```sql
CREATE TABLE fan_pages (
    id TEXT PRIMARY KEY,              -- Facebook Page ID
    access_token TEXT NOT NULL,       -- Page Access Token
    page_name TEXT NOT NULL,          -- Page Display Name
    page_avatar TEXT,                 -- Page Avatar URL
    delete_mode INTEGER DEFAULT 0,    -- 0=Hide, 1=Delete
    cleaner_enabled INTEGER DEFAULT 0 -- 0=Off, 1=On
)
```

#### reply_rules
```sql
CREATE TABLE reply_rules (
    rule_id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id TEXT NOT NULL,            -- FK to fan_pages.id
    trigger_words TEXT NOT NULL,      -- Comma-separated keywords
    reply_text TEXT NOT NULL,         -- Reply message
    image_path TEXT,                  -- Filename in uploads/
    FOREIGN KEY (page_id) REFERENCES fan_pages(id) ON DELETE CASCADE
)
```

#### apps
```sql
CREATE TABLE apps (
    app_id TEXT PRIMARY KEY,          -- Facebook App ID
    app_name TEXT NOT NULL,           -- App Display Name
    app_token TEXT NOT NULL           -- App Access Token
)
```

### Configuration

**File**: `settings.php`

```php
class Settings {
    public static $password = "qwerty";           // Admin password
    public static $dbFilePath = __DIR__."/comments.db";
    public static $fbApiVersion = 22;             // Graph API version
    public static $debug = true;                  // Debug mode flag
}
```

**Debug Mode Effects**:
- Displays PHP errors on screen
- Shows colored log messages in browser
- Enables detailed error reporting

### API Endpoints

#### Webhook Endpoint
- **URL**: `/index.php`
- **Methods**: GET (verification), POST (webhook data)
- **Purpose**: Receives Facebook webhook notifications

**GET Parameters**:
- `hub_mode`: "subscribe"
- `hub_challenge`: Verification token
- `hub_verify_token`: Expected verify token

**POST Body**: JSON webhook payload with comment data

#### Admin Endpoints
- **URL**: `/admin.php?page={section}`
- **Sections**: apps, pages, cleaner, replies
- **Authentication**: Session-based

### File Uploads

**Directory**: `/uploads/`

**Allowed Types**:
- `image/jpeg` (.jpg, .jpeg)
- `image/png` (.png)
- `image/gif` (.gif)

**Naming**: `{uniqid()}.{extension}`

**Storage**: Filename stored in database, file stored in `/uploads/`

**Usage**: Attached to comment replies via Facebook API photo upload

### Logging

**Directory**: `/logs/`

**File Format**: `YYYY-MM-DD.log`

**Entry Format**: `[HH:MM:SS] [Level] Message`

**Levels**: Trace, Info, Warning, Error

**Viewing**: Web-based log viewer with date range and level filtering

### Facebook Permissions Required

#### User Token (for fetching pages)
- `pages_manage_posts`: Manage page posts
- `pages_read_engagement`: Read page engagement data

#### Page Token (for operations)
- Automatically obtained when user grants access
- Never expires (if generated properly)
- Used for all page-level API calls

#### App Token
- Used for webhook subscriptions
- Format: `{app_id}|{app_secret}` or from Graph API

### Error Handling Strategy

1. **Database Errors**: Caught, logged, return false/empty array
2. **API Errors**: Checked in response, logged with details
3. **File Upload Errors**: Validated, logged, return false
4. **Session Errors**: Redirect to login
5. **Fatal Errors**: Logged with die() flag to stop execution

### Performance Considerations

- **Single Database File**: All data in one SQLite file (lightweight)
- **No Caching**: Direct database queries (acceptable for small scale)
- **Synchronous Processing**: Webhooks processed in real-time
- **Pagination**: Used for bulk content deletion to avoid timeouts
- **Token Validation**: Performed on page load (could be cached)

### Deployment Requirements

1. **PHP 7.4+** with extensions:
   - SQLite3
   - cURL
   - JSON
   - Sessions
   - File upload support

2. **Web Server**: Apache/Nginx with PHP support

3. **HTTPS Required**: Facebook webhooks require HTTPS

4. **File Permissions**:
   - Write access to `/logs/`
   - Write access to `/uploads/`
   - Write access to `comments.db`

5. **Facebook Configuration**:
   - App created in Facebook Developers
   - Webhook URL configured
   - Required permissions granted

### Known Limitations

1. **Single Admin User**: Only one password, no multi-user support
2. **No Rate Limiting**: API calls not rate-limited (could hit Facebook limits)
3. **No Backup System**: Database not automatically backed up
4. **No Queue System**: Webhook processing is synchronous
5. **Limited Error Recovery**: Failed actions not retried automatically
6. **No Analytics**: No built-in statistics or reporting
7. **Image Storage**: Images stored locally, no cloud integration

### Security Considerations

1. **Token Storage**: Tokens stored in plaintext in database (encrypt in production)
2. **Password Storage**: Password in plaintext config file (hash in production)
3. **SSL Verification**: Disabled in cURL for development (enable in production)
4. **Debug Mode**: Should be disabled in production
5. **Error Disclosure**: Debug mode exposes system details
6. **File Upload**: Limited validation (add size limits, virus scanning)

---

**Project URL**: https://yellowweb.top/yourowninstantcommentscleaner/

**Author**: YellowWeb (https://yellowweb.top)

**License**: Donations requested (USDT, Bitcoin, Ethereum addresses in README)
