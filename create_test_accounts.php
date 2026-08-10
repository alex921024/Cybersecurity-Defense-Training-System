<?php
header('Content-Type: text/html; charset=utf-8');

require_once 'api/db_connect.php';

try {
    
    // 教師帳號
    $teacher_username = 'TEACHER';
    $teacher_password_raw = 'TEACHER1024';
    $teacher_hash = password_hash($teacher_password_raw, PASSWORD_ARGON2ID);

    // 管理員帳號
    $admin_username = 'ALEX1024';
    $admin_password_raw = 'Aa0988709314';
    $admin_hash = password_hash($admin_password_raw, PASSWORD_ARGON2ID);

    // 學生帳號
    $student_username = 'STUDENT';
    $student_password_raw = 'STUDENT1024';
    $student_hash = password_hash($student_password_raw, PASSWORD_ARGON2ID);


    $sql = "INSERT INTO users (username, password_hash, role) VALUES 
            (?, ?, 'teacher'), 
            (?, ?, 'admin'),
            (?, ?, 'student')";
            
    $stmt = $pdo->prepare($sql);
    
    // 執行 SQL 寫入
    $stmt->execute([
        $teacher_username, $teacher_hash, 
        $admin_username, $admin_hash,
        $student_username, $student_hash
    ]);

    // 成功訊息
    echo "<div style='font-family: monospace; background: #111; color: #00FF00; padding: 20px; border-radius: 5px;'>";
    echo "<h2>✅ 測試帳號建立成功！</h2>";
    echo "<p>SQL 寫入已完成，密碼已成功使用 Argon2id 加密。</p>";
    echo "<ul>";
    echo "<li><strong>[教師權限]</strong> 帳號: <code>TEACHER</code> | 密碼: <code>TEACHER1024</code></li>";
    echo "<li><strong>[管理員權限]</strong> 帳號: <code>ALEX1024</code> | 密碼: <code>Aa0988709314</code></li>";
    echo "</ul>";
    echo "<p>👉 現在您可以回到 <a href='login.html' style='color: #00ebff;'>login.html</a> 進行登入測試了！</p>";
    echo "<p style='color: #ff4444;'>⚠️ 提示：為確保安全，測試完成後建議將此檔案 (create_test_accounts.php) 從資料夾中刪除。</p>";
    echo "</div>";

} catch (PDOException $e) {
    // 捕捉可能發生的錯誤 (例如帳號已存在)
    echo "<div style='font-family: monospace; background: #111; color: #ff4444; padding: 20px;'>";
    echo "<h2>❌ 建立失敗</h2>";
    echo "<p>錯誤訊息：" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>