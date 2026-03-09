<?php
// /tmp/db_setup_chats.php

$mysqli = new mysqli("localhost", "root", "", "broxbhai");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// 1. Create ai_conversations table
$sql1 = "CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_token VARCHAR(100) NULL,
    status ENUM('open', 'resolved', 'closed') DEFAULT 'open',
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (guest_token),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// 2. Create ai_messages table
$sql2 = "CREATE TABLE IF NOT EXISTS ai_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    role ENUM('system', 'user', 'assistant', 'admin') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    INDEX (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql1) && $mysqli->query($sql2)) {
    echo "AI Chat tables created successfully.";
} else {
    echo "Error: " . $mysqli->error;
}

$mysqli->close();
