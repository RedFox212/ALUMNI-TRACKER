<?php
// includes/db.php
require_once __DIR__ . '/../config.php';

try {
    // Initial connection to check database existence
    $check_dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $temp_pdo = new PDO($check_dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Check if database exists
    $stmt = $temp_pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        // Auto-import seed.sql
        $seed_file = __DIR__ . '/../seed.sql';
        if (file_exists($seed_file)) {
            $temp_pdo->exec("CREATE DATABASE " . DB_NAME);
            $temp_pdo->exec("USE " . DB_NAME);
            
            $sql = file_get_contents($seed_file);
            // Simple split by semicolon (careful with triggers/stored procs, but okay for LATS schema)
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($queries as $query) {
                if (!empty($query)) {
                    $temp_pdo->exec($query);
                }
            }
        }
    }

    // Standard connection for the app
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Auto-migrate: ensure new tables exist on existing DB (safe to run repeatedly)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS batch_officers (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            position    VARCHAR(100) DEFAULT NULL,
            batch_year  YEAR DEFAULT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS jobs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            posted_by   INT NOT NULL,
            title       VARCHAR(255) NOT NULL,
            company     VARCHAR(255) NOT NULL,
            location    VARCHAR(100) DEFAULT 'Remote',
            job_type    ENUM('Full-time','Part-time','Freelance','Internship') DEFAULT 'Full-time',
            salary      VARCHAR(50) DEFAULT NULL,
            description TEXT NOT NULL,
            apply_link  VARCHAR(255) DEFAULT NULL,
            is_active   TINYINT(1) DEFAULT 1,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS businesses (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            owner_id    INT NOT NULL,
            biz_name    VARCHAR(255) NOT NULL,
            category    VARCHAR(100) DEFAULT 'Others',
            description TEXT,
            logo_path   VARCHAR(255) DEFAULT NULL,
            website     VARCHAR(255) DEFAULT NULL,
            contact_no  VARCHAR(50) DEFAULT NULL,
            is_verified TINYINT(1) DEFAULT 0,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS event_rsvps (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            event_id    INT NOT NULL,
            user_id     INT NOT NULL,
            status      ENUM('Going','Maybe','Declined') DEFAULT 'Going',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rsvp (event_id, user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Fix for existing tables missing new columns
    // Separate individual try blocks to ensure one missing feature doesn't block the rest
    try { $pdo->exec("ALTER TABLE batch_officers ADD COLUMN position VARCHAR(100) DEFAULT NULL AFTER user_id"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE batch_officers ADD COLUMN batch_year YEAR DEFAULT NULL AFTER position"); } catch(Exception $e){}
    
    // 6. Verification Status for Alumni (Waitlist)
    try { $pdo->exec("ALTER TABLE alumni ADD COLUMN verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'"); } catch(Exception $e){}

    // 7. Verification for Businesses
    try { $pdo->exec("ALTER TABLE businesses ADD COLUMN status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'"); } catch(Exception $e){}

    // 8. Verification for Annoucements/Spotlights (Moderation)
    try { $pdo->exec("ALTER TABLE announcements ADD COLUMN status ENUM('pending', 'verified', 'rejected') DEFAULT 'verified'"); } catch(Exception $e){}

    // Add mentorship columns to alumni
    try { $pdo->exec("ALTER TABLE alumni ADD COLUMN mentor_status ENUM('none','pending','approved','declined') DEFAULT 'none' AFTER is_mentor"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE alumni ADD COLUMN mentor_bio TEXT DEFAULT NULL AFTER mentor_status"); } catch(Exception $e){}
    
    // 9. Audit Logs (Security Tracking)
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255),
        details TEXT,
        target_type VARCHAR(50),
        target_id INT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 10. Digital Alumni ID Data
    try { $pdo->exec("ALTER TABLE alumni ADD COLUMN alumni_id_num VARCHAR(20) UNIQUE AFTER user_id"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE alumni ADD COLUMN id_qr_secret VARCHAR(100) AFTER alumni_id_num"); } catch(Exception $e){}

    // 11. Spotlight Enhancement (Media Support)
    try { $pdo->exec("ALTER TABLE spotlights ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER achievement"); } catch(Exception $e){}

    // 12. Professional Portfolio (Website)
    // Check if column exists first to avoid unnecessary errors
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM alumni LIKE 'website'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE alumni ADD COLUMN website VARCHAR(255) DEFAULT NULL");
        }
        $stmt2 = $pdo->query("SHOW COLUMNS FROM alumni LIKE 'skills'");
        if (!$stmt2->fetch()) {
            $pdo->exec("ALTER TABLE alumni ADD COLUMN skills TEXT DEFAULT NULL");
        }
    } catch(Exception $e) {
        // Migration logging
    }
} catch (PDOException $e) {


    // Fail gracefully
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        die("Critical: Database not found and auto-import failed. Please manually import seed.sql via phpMyAdmin.");
    }
    die("Database connection failed: " . $e->getMessage());
}
?>
