<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selection page|Bina Usaha Sauyunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="shortcut icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="card ">
        <div class="top">
            <img src="../assets/logo.png" alt="logo">
            <p>
                Email :
                <a href="mailto:kop.binausahasauyunan@gmail.com">kop.binausahasauyunan@gmail.com</a>
            </p>
        </div>
        <div class="bottom">
            <div class="button-group">
                <a href="../transaksi-pembelian-food/index.php" class="btn link-slide slide-left">Food Cost</a>
                <a href="../addcost/transaksi-pembelian-add/index.php" class="btn link-slide slide-right">Add Cost</a>
                <a href="../profile/index.php" class="btn link-slide slide-right">Profile Koperasi</a>
            </div>
            <p>@ KOPERASI BINA USAHA SAUYUNAN|KBUS 2026</p>
        </div>
    </div>
</body>

</html>



<?php
session_start();

if (isset($_SESSION['success'])) {
?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Anda berhasil login',
            text: '<?= $_SESSION['success']; ?>',
            timer: 1500,
            showConfirmButton: false
        });
    </script>
<?php
    unset($_SESSION['success']);
}
?>