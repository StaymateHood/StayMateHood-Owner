# 🏢 Owner Community Chat - Firebase Integration Setup

## Firebase Configuration

### Step 1: Get Your Firebase Credentials

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project
3. Go to **Project Settings** → **Service Accounts**
4. Click **Generate New Private Key**
5. Copy your **Realtime Database URL** (format: `https://your-project-id-default-rtdb.firebaseio.com/`)

### Step 2: Update Firebase Config

Edit `firebase_config.php` and update:

```php
$this->firebase_url = "https://your-project-id-default-rtdb.firebaseio.com/";
```

Or set environment variables:
```bash
export FIREBASE_URL="https://your-project-id-default-rtdb.firebaseio.com/"
export FIREBASE_KEY="your-firebase-api-key"
```

### Step 3: Firebase Database Structure

Messages are stored in Firebase Realtime Database under:
```
owner_community/
├── {community_id}/
│   ├── messages/
│   │   ├── {message_key}/
│   │   │   ├── community_id: 1
│   │   │   ├── sender_id: 123
│   │   │   ├── sender_name: "John Doe"
│   │   │   ├── sender_image: "url"
│   │   │   ├── message: "Hello everyone!"
│   │   │   ├── message_type: "text"
│   │   │   ├── timestamp: 1704067200000
│   │   │   ├── sent_at: "2024-01-01 10:00:00"
│   │   │   └── is_deleted: false
│   └── last_message/
│       ├── message: "Latest message"
│       ├── timestamp: 1704067200000
│       └── sender_name: "John Doe"
```

## Database Setup

### MySQL Tables

Run the database schema:
```sql
-- Owner communities table
CREATE TABLE owner_communities (
    community_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    community_type ENUM('public', 'private') DEFAULT 'public',
    last_message TEXT,
    last_message_time TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (created_by) REFERENCES USERS(user_id) ON DELETE CASCADE
);

-- Community members table
CREATE TABLE owner_community_members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    owner_id INT NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (community_id) REFERENCES owner_communities(community_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (community_id, owner_id)
);

-- Indexes
CREATE INDEX idx_owner_communities_created_by ON owner_communities(created_by);
CREATE INDEX idx_owner_community_members_community ON owner_community_members(community_id);
CREATE INDEX idx_owner_community_members_owner ON owner_community_members(owner_id);
```

## API Endpoints

### 1. Create Community
```
POST /owner_community/create_community.php
{
    "name": "Property Owners Group",
    "description": "Discussion for property owners",
    "created_by": 123,
    "community_type": "public"
}
```

### 2. Get All Communities (for owner)
```
GET /owner_community/get_communities.php?owner_id=123
```

### 3. Get Community Members
```
GET /owner_community/get_community_members.php?community_id=1
```

### 4. Join Community
```
POST /owner_community/join_community.php
{
    "community_id": 1,
    "owner_id": 123
}
```

### 5. Leave Community
```
POST /owner_community/leave_community.php
{
    "community_id": 1,
    "owner_id": 123
}
```

### 6. Send Message (stored in Firebase)
```
POST /owner_community/send_message.php
{
    "community_id": 1,
    "sender_id": 123,
    "message": "Hello everyone!",
    "message_type": "text"
}
```

### 7. Get Messages (from Firebase)
```
GET /owner_community/get_messages.php?community_id=1&owner_id=123&limit=50
```

### 8. Discover Communities
```
GET /owner_community/discover_communities.php?owner_id=123&search=property
```

## Testing

### Using HTML Test Interface
1. Open `http://localhost/NEWAPI/owner_community/test.html`
2. Enter an Owner ID (from USERS table where user_type = 'Owner')
3. Create communities, join others, and test chat
4. View members in the Members tab

### Using cURL

**Create Community:**
```bash
curl -X POST http://localhost/NEWAPI/owner_community/create_community.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Community",
    "description": "Test description",
    "created_by": 1,
    "community_type": "public"
  }'
```

**Send Message:**
```bash
curl -X POST http://localhost/NEWAPI/owner_community/send_message.php \
  -H "Content-Type: application/json" \
  -d '{
    "community_id": 1,
    "sender_id": 1,
    "message": "Hello!",
    "message_type": "text"
  }'
```

**Get Messages:**
```bash
curl "http://localhost/NEWAPI/owner_community/get_messages.php?community_id=1&owner_id=1"
```

**Get Community Members:**
```bash
curl "http://localhost/NEWAPI/owner_community/get_community_members.php?community_id=1"
```

## Features

✅ **Messages in Firebase** - Real-time message storage
✅ **Community Management** - Create, join, leave communities
✅ **Member Management** - View all members with details
✅ **Role-based Access** - Admin and member roles
✅ **Owner-only** - Only owners can participate
✅ **Real-time Chat** - Live messaging with polling
✅ **Search & Discover** - Find public communities
✅ **Last Message Preview** - Quick community overview

## Troubleshooting

### Messages not saving to Firebase
- Check Firebase URL is correct
- Verify Firebase Realtime Database is enabled
- Check Firebase security rules allow write access

### Members not loading
- Verify community_id is correct
- Check owner is a member of the community
- Verify database connection

### Chat not updating
- Check message polling interval (default 3 seconds)
- Verify Firebase connection
- Check browser console for errors

## Security Rules (Firebase)

Set these rules in Firebase Console:

```json
{
  "rules": {
    "owner_community": {
      "$community_id": {
        "messages": {
          ".read": true,
          ".write": true,
          "$message_id": {
            ".validate": "newData.hasChildren(['sender_id', 'message', 'timestamp'])"
          }
        },
        "last_message": {
          ".read": true,
          ".write": true
        }
      }
    }
  }
}
```

---

**Ready to use! Start testing with test.html** 🚀