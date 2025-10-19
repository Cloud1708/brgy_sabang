<?php
// This file handles the token submission from email links
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Processing Password Reset...</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>
        <form id="tokenForm" method="post" action="reset_password">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        </form>
        <script>
            // Automatically submit the form to send token via POST
            document.getElementById('tokenForm').submit();
        </script>
    </body>
    </html>
    <?php
} else {
    // If no token provided, redirect to forgot password
    header('Location: forgot_password');
    exit;
}
?>
