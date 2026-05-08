<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');
include '../dbconn.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid user_id required']);
    exit;
}

function getGroupName($conn, $id) {
    $stmt = $conn->prepare("SELECT name FROM community_groups WHERE group_id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['name'] : 'Group ' . $id;
}

function getUserName($conn, $id) {
    $stmt = $conn->prepare("SELECT name FROM USERS WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['name'] : 'Unknown';
}

$firebaseUrl = 'https://sand111.firebaseio.com/chats.json?auth=AIzaSyC75jVwyAQ_shMjLgZMYmROiJZlm6nUtEE';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $firebaseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode(['success' => false, 'message' => 'Firebase access error', 'http_code' => $httpCode]);
    exit;
}

$allChats = json_decode($response, true);

if (!$allChats || !is_array($allChats)) {
    echo json_encode(['success' => true, 'chats' => [], 'total' => 0]);
    exit;
}

$userChats = [];
$nameCache = [];

foreach ($allChats as $chatId => $chatData) {
    if (!isset($chatData['messages']) || !is_array($chatData['messages'])) {
        continue;
    }

    $parts = explode('_', $chatId);
    $isUserInChat = (count($parts) == 2) &&
                    (intval($parts[0]) == $user_id || intval($parts[1]) == $user_id);

    if (!$isUserInChat) {
        continue;
    }

    $messages = [];
    $unreadCount = 0; // ✅ Count messages with status="sent" for receiver

    foreach ($chatData['messages'] as $msgId => $msg) {
        $senderId   = isset($msg['senderId'])   ? intval($msg['senderId'])   : 0;
        $receiverId = isset($msg['receiverId']) ? intval($msg['receiverId']) : 0;
        $status     = isset($msg['status'])     ? $msg['status']             : 'NA'; // ✅ NEW

        if (!isset($nameCache[$senderId])) {
            $nameCache[$senderId] = getUserName($conn, $senderId);
        }

        // ✅ Count unread: receiver is current user AND status is "sent"
        if ($receiverId == $user_id && $status === 'sent') {
            $unreadCount++;
        }

        $messages[] = [
            'message_id'   => $msgId,
            'sender_id'    => $senderId,
            'sender_name'  => $nameCache[$senderId],
            'receiver_id'  => $receiverId,
            'message'      => isset($msg['text'])      ? $msg['text']      : '',
            'message_type' => isset($msg['type'])      ? $msg['type']      : 'text',
            'timestamp'    => isset($msg['timestamp']) ? $msg['timestamp'] : 0,
            // 'status'       => $status, // ✅ NEW
            'is_mine'      => ($senderId == $user_id),
        ];
    }

    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });

    $otherUserId = (intval($parts[0]) == $user_id) ? intval($parts[1]) : intval($parts[0]);

    if (!isset($nameCache[$otherUserId])) {
        $nameCache[$otherUserId] = getUserName($conn, $otherUserId);
    }

    $userChats[] = [
        'chat_id'          => $chatId,
        'other_user_id'    => $otherUserId,
        'other_user_name'  => $nameCache[$otherUserId],
        'messages'         => $messages,
        'total_messages'   => count($messages),
        'unread_count'     => $unreadCount, // ✅ NEW: Unread message count
        'last_message'     => end($messages),
    ];
}

// Fetch groups from Firebase
$groupsUrl = 'https://sand111.firebaseio.com/groups.json?auth=AIzaSyC75jVwyAQ_shMjLgZMYmROiJZlm6nUtEE';
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $groupsUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$groupsResponse = curl_exec($ch2);
curl_close($ch2);

$allGroups = json_decode($groupsResponse, true);
$userGroups = [];

if ($allGroups && is_array($allGroups)) {
    foreach ($allGroups as $groupId => $groupData) {
        if (!is_array($groupData)) continue;

        $members = isset($groupData['members']) ? $groupData['members'] : [];
        if (!isset($members[(string)$user_id]) && !isset($members[$user_id])) continue;

        $messages = [];
        if (isset($groupData['messages']) && is_array($groupData['messages'])) {
            foreach ($groupData['messages'] as $msgId => $msg) {
                // support both field formats
                $senderId   = isset($msg['senderId'])   ? intval($msg['senderId'])   : (isset($msg['sender_id'])   ? intval($msg['sender_id'])   : 0);
                $senderName = isset($msg['senderName']) ? $msg['senderName']         : (isset($nameCache[$senderId]) ? $nameCache[$senderId] : getUserName($conn, $senderId));
                $nameCache[$senderId] = $senderName;
                $text       = isset($msg['text'])       ? $msg['text']               : (isset($msg['message'])     ? $msg['message']             : '');
                $type       = isset($msg['type'])       ? $msg['type']               : (isset($msg['message_type'])? $msg['message_type']        : 'text');

                $messages[] = [
                    'message_id'   => $msgId,
                    'sender_id'    => $senderId,
                    'sender_name'  => $senderName,
                    'message'      => $text,
                    'message_type' => $type,
                    'timestamp'    => isset($msg['timestamp']) ? $msg['timestamp'] : 0,
                    'is_mine'      => ($senderId == $user_id),
                ];
            }
            usort($messages, function($a, $b) { return $a['timestamp'] - $b['timestamp']; });
        }

        $unreadCount = 0;
        if (isset($groupData['unread_counts'][(string)$user_id])) {
            $unreadCount = intval($groupData['unread_counts'][(string)$user_id]);
        }

        $userGroups[] = [
            'group_id'      => $groupId,
            'group_name'    => getGroupName($conn, intval($groupId)),
            'members'       => array_keys($members),
            'messages'      => $messages,
            'total_messages'=> count($messages),
            'unread_count'  => $unreadCount,
            'last_message'  => isset($groupData['last_message']) ? $groupData['last_message'] : (count($messages) ? end($messages)['message'] : ''),
            'last_message_time' => isset($groupData['last_message_time']) ? $groupData['last_message_time'] : 0,
        ];
    }
}

$conn->close();

echo json_encode([
    'success'  => true,
    'user_id'  => $user_id,
    'chats'    => $userChats,
    'total'    => count($userChats),
    'groups'   => $userGroups,
    'total_groups' => count($userGroups),
], JSON_PRETTY_PRINT);
