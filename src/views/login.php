<?php
session_start();
require_once '../includes/db.php'; // Fixed path to database connection

// Initialize an error message variable
$error_message = "";

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Session expirée. Veuillez réessayer.";
    } else {
        // Sanitize and validate inputs
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // Log login attempt for debugging
        error_log("Login attempt for email: $email");
        
        // Validate inputs
        if (empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Email valide et mot de passe sont requis.";
        } 
        else {
            try {
                // Fetch user data from the database with better error handling
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    // User not found
                    error_log("Login failed: No user found with email $email");
                    $error_message = "Invalid email.";
                } else {
                    // Check if the password is properly hashed
                    if (password_verify($password, $user['password'])) {
                        error_log("Login successful for user: $email");
                        
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['group_id'] = $user['group_id'] ?? null;
                        redirectUserByRole($user['role']);
                    } else {
                        // Password doesn't match
                        error_log("Login failed: Incorrect password for user $email");
                        $error_message = "Mot de passe incorrect.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error during login: " . $e->getMessage());
                $error_message = "A system error occurred. Please try again later.";
            }
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
                            $yearName = "Première Année";
                            break;
                        case '2':
                            $yearName = "Deuxième Année";
                            break;
                        case '3':
                            $yearName = "Troisième Année";
                            break;
                        default:
                            $yearName = "Troisième Année";
                    }
                    $groupName = "G" . $group;
                    $_SESSION['group_id'] = $groupName;
                    $_SESSION['year_id'] = $yearName;
                    error_log("Student login redirection: Parsed group $groupNumeric as Year $yearName, Group $groupName");
                    header("Location: timetable_view.php?role=student&year=$yearName&group=$groupName");
                    exit;
                }
            }
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
            
            <!-- Only logo -->
            <div style="display: flex; justify-content: center; margin-bottom: 20px;">
              <img
                src="../assets/images/logo-supnum2.png"
                alt="SupNum"
                style="width: 80px; height: auto;"
              />
            </div>
            
            <!-- CSRF Protection -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
            
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
                class="toggle-password"
                alt="Toggle Password"
                id="togglePassword"
              />
            </div>
            <div class="forgot-password">
              <a href="#" class="forgot-link">Mot de passe oublié?</a>
            </div>
            <button type="submit">Connexion</button>
            <div class="error" style="color: red;font-size: 12px;min-height: 20px; margin-top: 10px; text-align: center; margin: -15px;position: relative;top: 5px;">
              <?php echo !empty($error_message) ? htmlspecialchars($error_message) : ''; ?>
            </div>
            <p class="terms-notice">
              En vous connectant, vous acceptez notre
              <a href="#" class="footer-link">Privacy Policy</a> et nos
              <a href="#" class="footer-link">Terms Of Services</a>.
            </p>
          </form>
        </div>
      </div>
      <!-- Background image section (without content) -->
      <div style="background: url(../assets/images/SupNum.jpg) no-repeat center center; background-size: cover; background-position: center; background-repeat: no-repeat;" class="description-section">
      </div>
    </div>
    <script src="../assets/js/main.js"></script>
  </body>
</html>
