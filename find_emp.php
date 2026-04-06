<?php
$conn = new mysqli("127.0.0.1", "dev_user", "dev_pass", "api_empresas");
if ($conn->connect_error) die($conn->connect_error);

$res = $conn->query("SELECT id_empresa, email FROM usuarios WHERE email = 'linyersaqb@gmail.com'");
print_r($res->fetch_all(MYSQLI_ASSOC));
$conn->close();
