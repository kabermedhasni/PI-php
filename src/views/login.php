<?php
session_start();
require_once '../includes/db.php'; // Fixed path to database connection

// Initialize an error message variable
$error_message = "";

// Check if there's an error parameter in the URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_role':
            $error_message = "You don't have permission to access that page.";
            break;
        case 'invalid_access':
            $error_message = "Invalid access attempt.";
            break;
        default:
            $error_message = "An error occurred. Please try again.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Log login attempt for debugging
    error_log("Login attempt for email: $email");
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } else {
        try {
            // Fetch user data from the database with better error handling
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // User not found
                error_log("Login failed: No user found with email $email");
                $error_message = "Invalid email or password.";
            } else {
                // Check if the password is stored as plain text (bad practice but might be the issue)
                if ($user['password'] === $password) {
                    // Plain text password match (not secure)
                    error_log("WARNING: Using plain text password comparison for user: $email");
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['group_id'] = $user['group_id'] ?? null;
                    
                    // For students, directly use the group_id to determine year and group
                    if ($user['role'] === 'student' && !empty($user['group_id'])) {
                        // Store the raw group_id for direct routing to timetable
                        error_log("Student login: Using group_id {$user['group_id']} for timetable routing");
                    }
                    
                    redirectUserByRole($user['role']);
                } 
                // Check if the password is properly hashed
                else if (password_verify($password, $user['password'])) {
                    error_log("Login successful for user: $email");
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['group_id'] = $user['group_id'] ?? null;
                    
                    // For students, directly use the group_id to determine year and group
                    if ($user['role'] === 'student' && !empty($user['group_id'])) {
                        // Store the raw group_id for direct routing to timetable
                        error_log("Student login: Using group_id {$user['group_id']} for timetable routing");
                    }
                    
                    redirectUserByRole($user['role']);
                } else {
                    // Password doesn't match
                    error_log("Login failed: Incorrect password for user $email");
                    $error_message = "Invalid email or password.";
                }
            }
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            $error_message = "A system error occurred. Please try again later.";
        }
    }
}

// Function to redirect user based on role
function redirectUserByRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: ../admin/index.php");
            break;
        case 'professor':
            header("Location: professor.php");
            break;
        case 'student':
            // For students, redirect directly to the timetable with their year and group
            if (isset($_SESSION['group_id'])) {
                // Group ID format assumption: First digit is year, second digit is group number
                // For example: 12 = Year 1, Group 2
                $groupNumeric = $_SESSION['group_id'];
                if (is_numeric($groupNumeric) && strlen($groupNumeric) >= 2) {
                    $year = substr($groupNumeric, 0, 1);
                    $group = substr($groupNumeric, 1, 1);
                    
                    // Convert to the format expected by timetable_view.php and the JSON files
                    // Use "First Year", "Second Year", etc. instead of "Y1", "Y2"
                    switch($year) {
                        case '1':
                            $yearName = "First Year";
                            break;
                        case '2':
                            $yearName = "Second Year";
                            break;
                        case '3':
                            $yearName = "Third Year";
                            break;
                        default:
                            $yearName = "First Year";
                    }
                    
                    $groupName = "G" . $group;
                    
                    error_log("Student login redirection: Parsed group $groupNumeric as Year $yearName, Group $groupName");
                    header("Location: timetable_view.php?role=student&year=$yearName&group=$groupName");
                    exit;
                }
            }
            // Fallback to standard student page if group ID format doesn't match expected pattern
            header("Location: student.php");
            break;
        default:
            echo "Unknown role.";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
      <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Timetable - Login</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
  </head>
  <body>
    <div class="container">
      <!-- Right side: Login form section with stars background -->
      <div class="login-section">
        <div class="stars-container" id="starsContainer">
          <form action="login.php" method="post">
            <div class="logo-placeholder"></div>
            <h5>Bienvenue</h5>
            <p class="subtitle">
              Connectez-vous pour consulter votre emploi du temps
            </p>
            <div class="input-container">
              <img
                src="../assets/images/email-svgrepo-com.svg"
                alt="Email Icon"
                class="input-icon"
              />
              <input
                type="email"
                name="email"
                placeholder="Adresse email"
                required
              />
            </div>
            <div class="input-container">
              <img
                src="../assets/images/lock-alt-svgrepo-com.svg"
                alt="Password Icon"
                class="input-icon"
              />
              <input
                type="password"
                name="password"
                id="password"
                placeholder="Mot de passe"
                required
              />
              <img
                src="../assets/images/eye-show-svgrepo-com.svg"
                alt="Toggle Password"
                class="toggle-password"
                id="togglePassword"
              />
            </div>
            <div class="forgot-password">
              <a href="#" class="forgot-link">Mot de passe oublié?</a>
            </div>
            <button type="submit">Connexion</button>
            <div class="error" style="color: red;font-size: 12px;min-height: 20px; margin-top: 10px; text-align: center; margin: -15px;position: relative;top: 5px;">
              <?php echo !empty($error_message) ? $error_message : ''; ?>
            </div>
            <p class="terms-notice">
              En vous connectant, vous acceptez notre
              <a href="#" class="footer-link">Privacy Policy</a> et nos
              <a href="#" class="footer-link">Terms Of Services</a>.
            </p>
          </form>
        </div>
      </div>
      <!-- Left side: Description section -->
      <div class="description-section">
        <div class="description-content">
          <img
            src="../assets/images/logo-supnum2.png"
            alt="SupNum"
            class="description-logo"
          />
          <h2>École Supérieure du Numérique</h2>
          <p>
            Plateforme de suivi pédagogique permettant aux étudiants de
            consulter leur emploi du temps.
          </p>
        </div>
      </div>
    </div>
    <script src="../assets/js/main.js"></script>
  </body>
</html>
