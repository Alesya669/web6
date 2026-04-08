<?php
/**
 * Задача 6. Административная панель с HTTP-авторизацией
 */

header('Content-Type: text/html; charset=UTF-8');

// Подключение к БД
require_once 'db_config.php';

// HTTP-авторизация администратора
$auth_success = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM admins WHERE login = ? AND pass_hash = MD5(?)");
        $stmt->execute([$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']]);
        if ($stmt->fetch()) {
            $auth_success = true;
        }
    } catch (PDOException $e) {
        $auth_success = false;
    }
}

if (!$auth_success) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>401 Требуется авторизация</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="container" style="max-width: 500px;">
            <h1>401 Требуется авторизация</h1>
            <p>Для доступа к административной панели необходимо ввести логин и пароль.</p>
            <p><strong>Логин:</strong> admin<br><strong>Пароль:</strong> admin123</p>
            <p><a href="index.php">← Вернуться на главную</a></p>
        </div>
    </body>
    </html>';
    exit();
}

// Обработка действий
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

// Удаление пользователя
if ($action === 'delete' && $user_id > 0) {
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM app_languages WHERE app_id = ?");
        $stmt->execute([$user_id]);
        $stmt = $db->prepare("DELETE FROM application WHERE id = ?");
        $stmt->execute([$user_id]);
        $db->commit();
        header('Location: admin.php?msg=deleted');
        exit();
    } catch (PDOException $e) {
        $error = "Ошибка удаления";
    }
}

// Редактирование пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $user_id > 0) {
    $errors = false;
    
    // Простая валидация
    if (empty($_POST['fullName']) || strlen($_POST['fullName']) > 150) $errors = true;
    if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $errors = true;
    if (empty($_POST['phone'])) $errors = true;
    if (empty($_POST['birthdate'])) $errors = true;
    if (empty($_POST['gender'])) $errors = true;
    if (empty($_POST['message']) || strlen($_POST['message']) < 4) $errors = true;
    if (empty($_POST['languages']) || !is_array($_POST['languages'])) $errors = true;
    
    if (!$errors) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE application SET fio = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract = ? WHERE id = ?");
            $stmt->execute([
                $_POST['fullName'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birthdate'],
                $_POST['gender'],
                $_POST['message'],
                isset($_POST['contract']) ? 1 : 0,
                $user_id
            ]);
            
            $stmt = $db->prepare("DELETE FROM app_languages WHERE app_id = ?");
            $stmt->execute([$user_id]);
            
            $lang_map = [];
            $langStmt = $db->query("SELECT id, code FROM languages");
            while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['code']] = $row['id'];
            }
            
            $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang) {
                if (isset($lang_map[$lang])) {
                    $insertLang->execute([$user_id, $lang_map[$lang]]);
                }
            }
            
            $db->commit();
            header('Location: admin.php?msg=updated');
            exit();
        } catch (PDOException $e) {
            $error = "Ошибка обновления";
        }
    }
}

// Получение данных для редактирования
$editUser = null;
if ($action === 'edit' && $user_id > 0) {
    $stmt = $db->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$user_id]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editUser) {
        $stmt = $db->prepare("SELECT l.code FROM languages l JOIN app_languages al ON l.id = al.lang_id WHERE al.app_id = ?");
        $stmt->execute([$user_id]);
        $editUser['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Получение списка пользователей
$users = [];
$stmt = $db->query("
    SELECT a.*, GROUP_CONCAT(l.code ORDER BY l.code) as languages_codes
    FROM application a
    LEFT JOIN app_languages al ON a.id = al.app_id
    LEFT JOIN languages l ON al.lang_id = l.id
    GROUP BY a.id
    ORDER BY a.id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика по языкам
$languageStats = [];
$stmt = $db->query("
    SELECT l.name, COUNT(al.app_id) as count
    FROM languages l
    LEFT JOIN app_languages al ON l.id = al.lang_id
    GROUP BY l.id
    ORDER BY count DESC
");
$languageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$languages_list = [
    'pascal' => 'Pascal', 'c' => 'C', 'cpp' => 'C++',
    'javascript' => 'JavaScript', 'php' => 'PHP', 'python' => 'Python',
    'java' => 'Java', 'haskell' => 'Haskell', 'clojure' => 'Clojure',
    'prolog' => 'Prolog', 'scala' => 'Scala', 'go' => 'Go'
];

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $message = '<div class="success-message">✿ Пользователь удален</div>';
    if ($_GET['msg'] == 'updated') $message = '<div class="success-message">✿ Данные обновлены</div>';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>༻❁༺Админ-панель༻❁༺</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Дополнительные стили для таблицы */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .admin-table th, .admin-table td {
            border: 1px solid #C2C5CE;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .admin-table th {
            background-color: #e8f4fd;
            font-weight: 600;
        }
        .admin-table tr:hover {
            background-color: #f5f5f5;
        }
        .btn-edit, .btn-delete {
            display: inline-block;
            padding: 5px 10px;
            margin: 2px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .btn-edit {
            background-color: #566777;
            color: white;
        }
        .btn-edit:hover {
            background-color: #475361;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .stats-box {
            background-color: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .lang-badge {
            display: inline-block;
            background-color: #e8f4fd;
            padding: 2px 8px;
            margin: 2px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #C2C5CE;
        }
        .modal {
            display: <?php echo $editUser ? 'block' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal-content {
            background: white;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #566777;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-save {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
        }
        .stats-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .stat-card {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #C2C5CE;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #566777;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-header">
        <h1>Панель администратора</h1>
        <div>
            <span>Вы вошли как <strong>✩admin✩</strong></span>
            <a href="index.php" style="margin-left: 15px; color: #566777;">← На главную</a>
        </div>
    </div>
    
    <?php echo $message; ?>
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Статистика -->
    <div class="stats-box">
        <h2>👑 Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div>всего пользователей</div>
            </div>
        </div>
        
        <h3>Популярность языков:</h3>
        <?php foreach ($languageStats as $lang): ?>
            <span class="lang-badge">
                <?php echo htmlspecialchars($lang['name']); ?>: <?php echo $lang['count']; ?>
            </span>
        <?php endforeach; ?>
    </div>
    
    <!-- Таблица пользователей -->
    <h2>❣ Все пользователи❣</h2>
    <?php if (empty($users)): ?>
        <p>Нет зарегистрированных пользователей</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Дата рожд.</th>
                    <th>Пол</th>
                    <th>Языки</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['fio']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td><?php echo date('d.m.Y', strtotime($user['birth_date'])); ?></td>
                    <td><?php echo $user['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
                    <td>
                        <?php 
                        $langs = explode(',', $user['languages_codes'] ?? '');
                        foreach ($langs as $lang):
                            if ($lang && isset($languages_list[$lang])):
                                echo '<span class="lang-badge">' . $languages_list[$lang] . '</span>';
                            endif;
                        endforeach;
                        ?>
                    </td>
                    <td>
                        <a href="admin.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit"> Редакт.</a>
                        <a href="admin.php?action=delete&id=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Удалить пользователя?')">🗑️ Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно редактирования -->
<?php if ($editUser): ?>
<div class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="window.location.href='admin.php'">&times;</span>
        <h2>✩Редактирование пользователя✩</h2>
        
        <form action="" method="POST">
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="fullName" value="<?php echo htmlspecialchars($editUser['fio']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($editUser['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Дата рождения *</label>
                <input type="date" name="birthdate" value="<?php echo $editUser['birth_date']; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Пол *</label>
                <div>
                    <label class="radio-group">
                        <input type="radio" name="gender" value="male" <?php echo $editUser['gender'] == 'male' ? 'checked' : ''; ?>> Мужской
                    </label>
                    <label class="radio-group">
                        <input type="radio" name="gender" value="female" <?php echo $editUser['gender'] == 'female' ? 'checked' : ''; ?>> Женский
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Языки программирования *</label>
                <select name="languages[]" multiple size="6">
                    <?php foreach ($languages_list as $code => $name): ?>
                        <option value="<?php echo $code; ?>" 
                            <?php echo in_array($code, $editUser['languages'] ?? []) ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="hint">Ctrl + клик для выбора нескольких</small>
            </div>
            
            <div class="form-group">
                <label>Биография *</label>
                <textarea name="message" required><?php echo htmlspecialchars($editUser['bio']); ?></textarea>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="contract" name="contract" <?php echo $editUser['contract'] ? 'checked' : ''; ?>>
                <label for="contract">С контрактом ознакомлен(а)</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">Сохранить</button>
                <a href="admin.php" class="btn-cancel">Отмена</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>
