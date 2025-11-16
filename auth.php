<?php
session_start();
require_once 'core/db.php'; // Fixed path to database connection
require_once 'core/auth_helper.php';

restore_session_from_cookie($pdo);

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    redirectUserByRole($_SESSION['role']);
}

// Initialize an error message variable
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
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
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];

                        if ($user['role'] === 'student' && !empty($user['group_id'])) {
                            $groupStmt = $pdo->prepare("SELECT g.name AS group_name, y.name AS year_name FROM `groups` g JOIN `years` y ON g.year_id = y.id WHERE g.id = ?");
                            $groupStmt->execute([$user['group_id']]);
                            $groupInfo = $groupStmt->fetch(PDO::FETCH_ASSOC);

                            if ($groupInfo) {
                                $_SESSION['group_id'] = $groupInfo['group_name'];
                                $_SESSION['year_id'] = $groupInfo['year_name'];
                            } else {
                                $_SESSION['group_id'] = null;
                                $_SESSION['year_id'] = null;
                            }
                        } else {
                            $_SESSION['group_id'] = null;
                            $_SESSION['year_id'] = null;
                        }

                        create_remember_me_cookie($user);

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



// Function to redirect user based on role
function redirectUserByRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: admin/index.php");
            break;
        case 'professor':
            header("Location: index.php");
            break;
        case 'student':
            header("Location: index.php");
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
    <title>Login Page</title>
    <link rel="icon" href="assets/images/logo-supnum.png" />
    <link rel="stylesheet" href="assets/css/pages/auth.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body>
    <div class="container">
      <!-- Right side: Login form section with stars background -->
      <div class="login-section">
        <div class="stars-container" id="starsContainer">
          <form action="auth.php" method="post">
            <div class="logo-placeholder"></div>
            
            <!-- Only logo -->
            <div class="logo-container">
              <img
                src="assets/images/logo-supnum.png"
                alt="SupNum"
                class="logo-image"
              />
            </div>
            
            <p class="subtitle">
              Connectez-vous pour consulter votre emploi du temps
            </p>
            <div class="input-container">
              <img
                src="assets/images/email.svg"
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
                src="assets/images/lock.svg"
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
                src="assets/images/eye-show.svg"
                class="toggle-password"
                alt="Toggle Password"
                id="togglePassword"
              />
            </div>
            <div class="forgot-password">
              <a href="#" class="forgot-link">Mot de passe oublié?</a>
            </div>
            <button type="submit">Connexion</button>
            <div class="error">
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
      <div class="description-section background-image">
      </div>
    </div>
    <script src="assets/js/auth.js"></script>
  </body>
</html>
