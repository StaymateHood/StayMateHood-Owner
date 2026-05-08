-- Owner Community Chat System Database Schema

-- Table for owner communities
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

-- Table for owner community members
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

-- Table for owner community messages
CREATE TABLE owner_community_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file') DEFAULT 'text',
    file_url VARCHAR(500),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,
    FOREIGN KEY (community_id) REFERENCES owner_communities(community_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES USERS(user_id) ON DELETE CASCADE
);

-- Indexes for better performance
CREATE INDEX idx_owner_communities_created_by ON owner_communities(created_by);
CREATE INDEX idx_owner_community_members_community ON owner_community_members(community_id);
CREATE INDEX idx_owner_community_members_owner ON owner_community_members(owner_id);
CREATE INDEX idx_owner_community_messages_community ON owner_community_messages(community_id);
CREATE INDEX idx_owner_community_messages_sent_at ON owner_community_messages(sent_at);