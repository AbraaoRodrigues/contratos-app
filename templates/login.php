<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../assets/css/style.css">
  <title>Login - Contratos Agudos</title>
</head>

<body>
  <div class="login-box">
    <h2>Login</h2>
    <form action="../api/auth/login.php" method="post">
      <input type="email" name="email" placeholder="E-mail" required><br>
      <input type="password" name="senha" placeholder="Senha" required><br>
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>

</html>
