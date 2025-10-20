<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container text-center" style="margin-top: 100px;">
        <div class="alert alert-danger">
            <h1 class="display-4">Payment Failed</h1>
            <p class="lead">Unfortunately, we were unable to process your payment.</p>
            <hr>
            <p>
                Error: <?php echo isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'An unknown error occurred.'; ?>
            </p>
            <a href="cart.php" class="btn btn-warning btn-lg mt-3">Try Again</a>
        </div>
    </div>
</body>
</html>