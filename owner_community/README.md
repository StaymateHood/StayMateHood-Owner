# 🏢 Owner Community Chat System

Complete owner-only community chat system for property owners to connect, share experiences, and communicate.

## 📁 Files Structure

```
owner_community/
├── database_schema.sql          # Database tables
├── create_community.php         # Create new community
├── get_communities.php          # Get owner's communities
├── join_community.php           # Join existing community
├── leave_community.php          # Leave community
├── send_message.php             # Send messages
├── get_messages.php             # Get chat messages
├── discover_communities.php     # Find public communities
├── test.html                    # Testing interface
└── README.md                    # This file
```

## 🗄️ Database Tables

### owner_communities
- `community_id` - Primary key
- `name` - Community name
- `description` - Community description
- `created_by` - Owner who created it
- `community_type` - 'public' or 'private'
- `last_message` - Latest message preview
- `last_message_time` - When last message was sent
- `created_at` - Creation timestamp
- `is_active` - Active status

### owner_community_members
- `member_id` - Primary key
- `community_id` - Community reference
- `owner_id` - Owner reference
- `role` - 'admin' or 'member'
- `joined_at` - Join timestamp
- `is_active` - Membership status

### owner_community_messages
- `message_id` - Primary key
- `community_id` - Community reference
- `sender_id` - Message sender
- `message` - Message content
- `message_type` - 'text', 'image', 'file'
- `file_url` - File attachment URL
- `sent_at` - Message timestamp
- `is_deleted` - Deletion status

## 🚀 API Endpoints

### 1. Create Community
```
POST /owner_community/create_community.php
Body: {
    "name": "Property Owners Group",
    "description": "Discussion for property owners",
    "created_by": 123,
    "community_type": "public"
}
```

### 2. Get My Communities
```
GET /owner_community/get_communities.php?owner_id=123
```

### 3. Discover Communities
```
GET /owner_community/discover_communities.php?owner_id=123&search=property
```

### 4. Join Community
```
POST /owner_community/join_community.php
Body: {
    "community_id": 1,
    "owner_id": 123
}
```

### 5. Leave Community
```
POST /owner_community/leave_community.php
Body: {
    "community_id": 1,
    "owner_id": 123
}
```

### 6. Send Message
```
POST /owner_community/send_message.php
Body: {
    "community_id": 1,
    "sender_id": 123,
    "message": "Hello everyone!",
    "message_type": "text"
}
```

### 7. Get Messages
```
GET /owner_community/get_messages.php?community_id=1&owner_id=123&limit=50&offset=0
```

## 🔧 Setup Instructions

### 1. Database Setup
```sql
-- Run the database_schema.sql file
mysql -u root -p your_database < database_schema.sql
```

### 2. Test the System
1. Open `test.html` in browser
2. Enter an Owner ID (from USERS table where user_type = 'Owner')
3. Create communities, join others, and test chat

### 3. Integration
Include the APIs in your application:
```javascript
// Example: Create community
const response = await fetch('/NEWAPI/owner_community/create_community.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        name: 'My Community',
        description: 'Community description',
        created_by: ownerId,
        community_type: 'public'
    })
});
```

## 🔒 Security Features

- **Owner-only access**: Only users with user_type = 'Owner' can participate
- **Membership verification**: All actions verify community membership
- **Admin protection**: Prevents last admin from leaving community
- **Input validation**: All inputs are validated and sanitized
- **SQL injection protection**: Prepared statements used throughout

## ✨ Features

- ✅ Create public/private communities
- ✅ Join/leave communities
- ✅ Real-time messaging
- ✅ Search/discover communities
- ✅ Admin/member roles
- ✅ Message history
- ✅ Member count tracking
- ✅ Last message preview
- ✅ Owner-only restriction

## 🧪 Testing

Use the `test.html` file to:
1. Create communities
2. Search and join communities
3. Send and receive messages
4. Test all API endpoints

## 📱 Frontend Integration

The APIs return JSON responses suitable for:
- React/Vue/Angular applications
- Mobile apps
- Real-time chat interfaces
- Community management dashboards

## 🔄 Real-time Updates

For real-time chat experience:
- Poll `get_messages.php` every 3-5 seconds
- Use WebSocket for instant messaging (future enhancement)
- Implement push notifications for new messages

## 🎯 Use Cases

- Property owner networking
- Experience sharing
- Market discussions
- Local area updates
- Investment opportunities
- Maintenance tips sharing

---

**Built for property owners to connect and collaborate! 🏠💬**