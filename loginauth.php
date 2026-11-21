<?php
require_once __DIR__ . '/app.php';
include __DIR__ . '/koneksi.php';

if (isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!csrf_validate()) {
        $loginError = 'Sesi login tidak valid. Silakan muat ulang halaman.';
    } else {
        $user = trim((string) ($_POST['user'] ?? ''));
        $pass = (string) ($_POST['pass'] ?? '');
        $stmt = mysqli_prepare($conn, 'SELECT id_admin, nama_admin, pass_admin FROM admin WHERE user_admin = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        $valid = $admin && (
            password_verify($pass, (string) $admin['pass_admin']) ||
            hash_equals((string) $admin['pass_admin'], $pass)
        );

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin'] = (int) $admin['id_admin'];
            $_SESSION['nama_admin'] = $admin['nama_admin'];

            if (!password_get_info((string) $admin['pass_admin'])['algo']) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $update = mysqli_prepare($conn, 'UPDATE admin SET pass_admin = ? WHERE id_admin = ?');
                mysqli_stmt_bind_param($update, 'si', $hash, $_SESSION['admin']);
                mysqli_stmt_execute($update);
            }
            header('Location: index.php');
            exit;
        }
        $loginError = 'Username atau password salah.';
    }
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Login</title>
    <link href="foto/logos.png" rel="icon" type="images/x-icon">
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern.css" rel="stylesheet">

</head>

<body class="bg-gradient-primary">

    <div class="container">

        <!-- Outer Row -->
        <div class="row justify-content-center">

            <div class="col-xl-10 col-lg-12 col-md-9">

                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <!-- Nested Row within Card Body -->
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block bg-login-image">
                                <img width="100%" height="100%" src="img/ava.jpeg">
                            </div>
                            <div class="col-lg-6">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">Silahkan Login</h1>
                                    </div>
                                    <?php if ($loginError): ?>
                                    <div class="alert alert-danger spp-alert" role="alert"><?= e($loginError) ?></div>
                                    <?php endif; ?>
                                    <form class="user" method="post" action="">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <div class="form-group">
                                            <input type="text" autocomplete="off" required name="user" class="form-control form-control-user"
                                                id="exampleInputEmail" aria-describedby="emailHelp"
                                                placeholder="Enter Username...">
                                        </div>
                                        <div class="form-group">
                                            <input autocomplete="off" type="password"required name="pass" class="form-control form-control-user"
                                                id="exampleInputPassword" placeholder="Password">
                                        </div>
                                        
                                        <button type="submit" name="login" class="btn btn-primary btn-user btn-block">Login</button>
                                        <hr>
                                    </form>
                                   
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>
    <script type="text/javascript">
       $('input').attr('autocomplete','off');
   </script>

</body>

</html>