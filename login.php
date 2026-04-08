<?php
/**
 * Файл login.php для не авторизованного пользователя выводит форму логина.
 * При отправке формы проверяет логин/пароль и создает сессию,
 * записывает в нее логин и id пользователя.
 */

header('Content-Type: text/html; charset=UTF-8');

// Подключение к БД
$user = 'u82278';
$pass = '3700374';
$db = new PDO('mysql:host=localhost;dbname=u82278', $user, $pass,
    [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Проверяем, есть ли уже активная сессия
$session_started = false;
if (!empty($_COOKIE[session_name()]) && session_start()) {
    $session_started = true;
    if (!empty($_SESSION['login'])) {
        // Пользователь уже авторизован
        header('Location: index.php');
        exit();
    }
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_start();
    session_destroy();
    setcookie(session_name(), '', time() - 3600);
    header('Location: login.php');
    exit();
}

// Сообщения об ошибках
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = $_POST['login'] ?? '';
    $pass = $_POST['pass'] ?? '';
    
    if (empty($login) || empty($pass)) {
        $error = 'Заполните логин и пароль';
    } else {
        // Ищем пользователя в БД
        try {
            $stmt = $db->prepare("SELECT id, login, pass_hash FROM application WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && md5($pass) === $user['pass_hash']) {
                // Успешная авторизация
                if (!$session_started) {
                    session_start();
                }
                $_SESSION['login'] = $user['login'];
                $_SESSION['uid'] = $user['id'];
                
                header('Location: index.php');
                exit();
            } else {
                $error = 'Неверный логин или пароль';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных';
        }
    }
}

// HTML форма входа
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        .login-title {
            color: #566777;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #566777;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #C2C5CE;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .submit-btn {
            width: 100%;
            background-color: #566777;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .submit-btn:hover {
            background-color: #475361;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        .back-link a {
            color: #566777;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">Вход в систему</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="" method="post">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="pass">Пароль</label>
                <input type="password" id="pass" name="pass" required>
            </div>
            
            <button type="submit" class="submit-btn">Войти</button>
        </form>
        
        <div class="back-link">
            <a href="index.php">← Вернуться к форме</a>
        </div>
    </div>
</body>
</html>
