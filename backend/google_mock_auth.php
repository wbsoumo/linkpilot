<?php
// backend/google_mock_auth.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt_helper.php';

$redirectUri = $_GET['redirect_uri'] ?? 'http://localhost:63806/';

$db = Database::getConnection();
$stmt = $db->query("SELECT id, name, email, role FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $selectedUser = null;
    foreach ($users as $u) {
        if ($u['id'] === $userId) {
            $selectedUser = $u;
            break;
        }
    }
    
    if ($selectedUser) {
        // Generate real JWT token
        $token = JWTHelper::generateToken([
            'id' => $selectedUser['id'],
            'email' => $selectedUser['email'],
            'name' => $selectedUser['name'],
            'role' => $selectedUser['role']
        ]);
        
        $userJson = json_encode([
            'id' => $selectedUser['id'],
            'name' => $selectedUser['name'],
            'email' => $selectedUser['email'],
            'role' => $selectedUser['role']
        ]);
        
        // Redirect back to flutter app
        $target = $redirectUri;
        if (strpos($target, '?') !== false) {
            $target .= "&token=" . urlencode($token) . "&user=" . urlencode($userJson);
        } else {
            // Support hashes in router URL structure
            if (strpos($target, '/#/login') !== false) {
                $target = str_replace('/#/login', '/#/login?token=' . urlencode($token) . '&user=' . urlencode($userJson), $target);
            } else if (strpos($target, '#') !== false) {
                $target .= "?token=" . urlencode($token) . "&user=" . urlencode($userJson);
            } else {
                $target .= "?token=" . urlencode($token) . "&user=" . urlencode($userJson);
            }
        }
        
        header("Location: " . $target);
        exit(0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - Google Accounts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white border border-gray-200 rounded-lg max-w-md w-full p-8 shadow-sm">
        <!-- Google Logo -->
        <div class="flex justify-center mb-6">
            <svg class="h-6" viewBox="0 0 74 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.24 11.2V8.34H16.48C16.58 8.84 16.64 9.38 16.64 10.02C16.64 14.4 13.72 17.52 9.26 17.52C4.16 17.52 0 13.36 0 8.26C0 3.16 4.16 1 9.26 1C11.76 1 13.86 1.92 15.48 3.44L13.34 5.52C12.8 5 11.36 3.96 9.26 3.96C5.74 3.96 2.86 6.88 2.86 10.46C2.86 14.04 5.74 16.96 9.26 16.96C13.32 16.96 14.84 14.12 15.1 12.62H9.24V11.2Z" fill="#4285F4"/>
                <path d="M26.4 8.76C29.46 8.76 32 11.24 32 14.36C32 17.48 29.46 19.96 26.4 19.96C23.34 19.96 20.8 17.48 20.8 14.36C20.8 11.24 23.34 8.76 26.4 8.76ZM26.4 17.42C28.08 17.42 29.34 16.02 29.34 14.36C29.34 12.7 28.08 11.3 26.4 11.3C24.72 11.3 23.46 12.7 23.46 14.36C23.46 16.02 24.72 17.42 26.4 17.42Z" fill="#EA4335"/>
                <path d="M39.6 8.76C42.66 8.76 45.2 11.24 45.2 14.36C45.2 17.48 42.66 19.96 39.6 19.96C36.54 19.96 34 17.48 34 14.36C34 11.24 36.54 8.76 39.6 8.76ZM39.6 17.42C41.28 17.42 42.54 16.02 42.54 14.36C42.54 12.7 41.28 11.3 39.6 11.3C37.92 11.3 36.66 12.7 36.66 14.36C36.66 16.02 37.92 17.42 39.6 17.42Z" fill="#FBBC05"/>
                <path d="M52.8 8.76C55.6 8.76 57.9 10.18 58.78 12.28L56.28 13.32C55.72 11.98 54.54 11.3 53.04 11.3C51.02 11.3 49.32 13.02 49.32 15.2C49.32 17.38 51.02 19.1 53.04 19.1C54.58 19.1 55.66 18.36 56.24 17.06L58.74 18.1C57.86 20.2 55.56 21.62 52.8 21.62C49.52 21.62 46.9 18.98 46.9 15.2C46.9 11.42 49.52 8.76 52.8 8.76Z" fill="#4285F4"/>
                <path d="M63 1C64.12 1 65 1.88 65 3V21.62H62.14V1H63Z" fill="#34A853"/>
                <path d="M72 8.76C75.06 8.76 77.6 11.24 77.6 14.36C77.6 17.48 75.06 19.96 72 19.96C68.94 19.96 66.4 17.48 66.4 14.36C66.4 11.24 68.94 8.76 72 8.76ZM72 17.42C73.68 17.42 74.94 16.02 74.94 14.36C74.94 12.7 73.68 11.3 72 11.3C70.32 11.3 69.06 12.7 69.06 14.36C69.06 16.02 70.32 17.42 72 17.42Z" fill="#EA4335"/>
            </svg>
        </div>

        <h1 class="text-2xl font-normal text-center text-gray-900 mb-2">Choose an account</h1>
        <p class="text-sm text-gray-600 text-center mb-6">to continue to <span class="font-medium text-gray-800">LinkPilot AI Portal</span></p>

        <!-- Accounts List -->
        <form method="POST">
            <div class="border border-gray-200 rounded-lg overflow-hidden mb-6">
                <?php foreach ($users as $index => $u): ?>
                    <button type="submit" name="user_id" value="<?php echo $u['id']; ?>" class="w-full flex items-center p-4 hover:bg-gray-50 transition border-b border-gray-100 last:border-0 text-left">
                        <div class="h-10 w-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-lg mr-4">
                            <?php echo strtoupper($u['name'][0]); ?>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($u['name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['email']); ?></div>
                        </div>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded font-medium capitalize">
                            <?php echo htmlspecialchars($u['role']); ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>

        <!-- Info footer -->
        <div class="text-xs text-gray-500 leading-relaxed">
            To create a new sandbox user, register via the LinkPilot sign-up page or contact support. LinkPilot sandbox utilizes standard Google OAuth standards.
        </div>
    </div>
</body>
</html>
