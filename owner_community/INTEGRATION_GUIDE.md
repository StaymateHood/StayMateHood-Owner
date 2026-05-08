# 🏢 Owner Community Chat - Integration Guide

## Frontend Integration Examples

### 1. React Integration

```jsx
import React, { useState, useEffect } from 'react';

const OwnerCommunityChat = ({ ownerId }) => {
    const [communities, setCommunities] = useState([]);
    const [selectedCommunity, setSelectedCommunity] = useState(null);
    const [messages, setMessages] = useState([]);
    const [members, setMembers] = useState([]);
    const [messageInput, setMessageInput] = useState('');
    const API_BASE = '/NEWAPI/owner_community/';

    // Get communities
    useEffect(() => {
        fetchCommunities();
    }, [ownerId]);

    const fetchCommunities = async () => {
        const response = await fetch(`${API_BASE}get_communities.php?owner_id=${ownerId}`);
        const data = await response.json();
        if (data.success) {
            setCommunities(data.communities);
        }
    };

    // Get messages when community selected
    useEffect(() => {
        if (selectedCommunity) {
            fetchMessages();
            const interval = setInterval(fetchMessages, 3000);
            return () => clearInterval(interval);
        }
    }, [selectedCommunity]);

    const fetchMessages = async () => {
        const response = await fetch(
            `${API_BASE}get_messages.php?community_id=${selectedCommunity.community_id}&owner_id=${ownerId}`
        );
        const data = await response.json();
        if (data.success) {
            setMessages(data.messages);
        }
    };

    const fetchMembers = async () => {
        const response = await fetch(
            `${API_BASE}get_community_members.php?community_id=${selectedCommunity.community_id}`
        );
        const data = await response.json();
        if (data.success) {
            setMembers(data.members);
        }
    };

    const sendMessage = async () => {
        if (!messageInput.trim()) return;

        const response = await fetch(`${API_BASE}send_message.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                community_id: selectedCommunity.community_id,
                sender_id: ownerId,
                message: messageInput,
                message_type: 'text'
            })
        });

        const data = await response.json();
        if (data.success) {
            setMessageInput('');
            fetchMessages();
        }
    };

    return (
        <div className="owner-community-chat">
            <div className="communities-list">
                {communities.map(community => (
                    <div
                        key={community.community_id}
                        onClick={() => setSelectedCommunity(community)}
                        className={selectedCommunity?.community_id === community.community_id ? 'active' : ''}
                    >
                        <h4>{community.name}</h4>
                        <p>{community.member_count} members</p>
                    </div>
                ))}
            </div>

            {selectedCommunity && (
                <div className="chat-area">
                    <div className="messages">
                        {messages.map(msg => (
                            <div key={msg.firebase_key} className={msg.sender_id === ownerId ? 'my-message' : 'other-message'}>
                                <strong>{msg.sender_name}</strong>
                                <p>{msg.message}</p>
                                <small>{new Date(msg.sent_at).toLocaleString()}</small>
                            </div>
                        ))}
                    </div>

                    <div className="message-input">
                        <input
                            value={messageInput}
                            onChange={(e) => setMessageInput(e.target.value)}
                            onKeyPress={(e) => e.key === 'Enter' && sendMessage()}
                            placeholder="Type message..."
                        />
                        <button onClick={sendMessage}>Send</button>
                    </div>

                    <button onClick={fetchMembers}>View Members</button>
                    {members.length > 0 && (
                        <div className="members-list">
                            {members.map(member => (
                                <div key={member.member_id}>
                                    <p>{member.name} ({member.role})</p>
                                    <small>{member.email}</small>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default OwnerCommunityChat;
```

### 2. Vue Integration

```vue
<template>
    <div class="owner-community-chat">
        <div class="communities-list">
            <div
                v-for="community in communities"
                :key="community.community_id"
                @click="selectCommunity(community)"
                :class="{ active: selectedCommunity?.community_id === community.community_id }"
            >
                <h4>{{ community.name }}</h4>
                <p>{{ community.member_count }} members</p>
            </div>
        </div>

        <div v-if="selectedCommunity" class="chat-area">
            <div class="messages">
                <div
                    v-for="msg in messages"
                    :key="msg.firebase_key"
                    :class="msg.sender_id === ownerId ? 'my-message' : 'other-message'"
                >
                    <strong>{{ msg.sender_name }}</strong>
                    <p>{{ msg.message }}</p>
                    <small>{{ formatDate(msg.sent_at) }}</small>
                </div>
            </div>

            <div class="message-input">
                <input
                    v-model="messageInput"
                    @keypress.enter="sendMessage"
                    placeholder="Type message..."
                />
                <button @click="sendMessage">Send</button>
            </div>

            <button @click="loadMembers">View Members</button>
            <div v-if="members.length > 0" class="members-list">
                <div v-for="member in members" :key="member.member_id">
                    <p>{{ member.name }} ({{ member.role }})</p>
                    <small>{{ member.email }}</small>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['ownerId'],
    data() {
        return {
            communities: [],
            selectedCommunity: null,
            messages: [],
            members: [],
            messageInput: '',
            API_BASE: '/NEWAPI/owner_community/'
        };
    },
    mounted() {
        this.fetchCommunities();
    },
    watch: {
        selectedCommunity() {
            if (this.selectedCommunity) {
                this.fetchMessages();
                this.messageInterval = setInterval(() => this.fetchMessages(), 3000);
            }
        }
    },
    methods: {
        async fetchCommunities() {
            const response = await fetch(`${this.API_BASE}get_communities.php?owner_id=${this.ownerId}`);
            const data = await response.json();
            if (data.success) {
                this.communities = data.communities;
            }
        },
        async fetchMessages() {
            const response = await fetch(
                `${this.API_BASE}get_messages.php?community_id=${this.selectedCommunity.community_id}&owner_id=${this.ownerId}`
            );
            const data = await response.json();
            if (data.success) {
                this.messages = data.messages;
            }
        },
        async sendMessage() {
            if (!this.messageInput.trim()) return;

            const response = await fetch(`${this.API_BASE}send_message.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    community_id: this.selectedCommunity.community_id,
                    sender_id: this.ownerId,
                    message: this.messageInput,
                    message_type: 'text'
                })
            });

            const data = await response.json();
            if (data.success) {
                this.messageInput = '';
                this.fetchMessages();
            }
        },
        async loadMembers() {
            const response = await fetch(
                `${this.API_BASE}get_community_members.php?community_id=${this.selectedCommunity.community_id}`
            );
            const data = await response.json();
            if (data.success) {
                this.members = data.members;
            }
        },
        selectCommunity(community) {
            this.selectedCommunity = community;
        },
        formatDate(date) {
            return new Date(date).toLocaleString();
        }
    },
    beforeUnmount() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
        }
    }
};
</script>
```

### 3. JavaScript Vanilla Integration

```javascript
class OwnerCommunityChat {
    constructor(ownerId, containerId) {
        this.ownerId = ownerId;
        this.container = document.getElementById(containerId);
        this.API_BASE = '/NEWAPI/owner_community/';
        this.selectedCommunity = null;
        this.messageInterval = null;
        this.init();
    }

    async init() {
        await this.fetchCommunities();
        this.render();
    }

    async fetchCommunities() {
        const response = await fetch(`${this.API_BASE}get_communities.php?owner_id=${this.ownerId}`);
        const data = await response.json();
        this.communities = data.success ? data.communities : [];
    }

    async fetchMessages() {
        if (!this.selectedCommunity) return;

        const response = await fetch(
            `${this.API_BASE}get_messages.php?community_id=${this.selectedCommunity.community_id}&owner_id=${this.ownerId}`
        );
        const data = await response.json();
        this.messages = data.success ? data.messages : [];
        this.renderMessages();
    }

    async sendMessage(text) {
        if (!text.trim() || !this.selectedCommunity) return;

        const response = await fetch(`${this.API_BASE}send_message.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                community_id: this.selectedCommunity.community_id,
                sender_id: this.ownerId,
                message: text,
                message_type: 'text'
            })
        });

        const data = await response.json();
        if (data.success) {
            await this.fetchMessages();
        }
    }

    async loadMembers() {
        if (!this.selectedCommunity) return;

        const response = await fetch(
            `${this.API_BASE}get_community_members.php?community_id=${this.selectedCommunity.community_id}`
        );
        const data = await response.json();
        this.members = data.success ? data.members : [];
        this.renderMembers();
    }

    selectCommunity(community) {
        this.selectedCommunity = community;
        if (this.messageInterval) clearInterval(this.messageInterval);
        this.fetchMessages();
        this.messageInterval = setInterval(() => this.fetchMessages(), 3000);
        this.render();
    }

    render() {
        this.container.innerHTML = `
            <div class="owner-community-chat">
                <div class="communities-list">
                    ${this.communities.map(c => `
                        <div class="community-item ${this.selectedCommunity?.community_id === c.community_id ? 'active' : ''}">
                            <h4>${c.name}</h4>
                            <p>${c.member_count} members</p>
                            <button onclick="chat.selectCommunity(${JSON.stringify(c).replace(/"/g, '&quot;')})">Select</button>
                        </div>
                    `).join('')}
                </div>
                ${this.selectedCommunity ? `
                    <div class="chat-area">
                        <div id="messages-container" class="messages"></div>
                        <div class="message-input">
                            <input id="message-input" type="text" placeholder="Type message..." />
                            <button onclick="chat.sendMessage(document.getElementById('message-input').value); document.getElementById('message-input').value = '';">Send</button>
                        </div>
                        <button onclick="chat.loadMembers()">View Members</button>
                        <div id="members-container"></div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderMessages() {
        const container = document.getElementById('messages-container');
        container.innerHTML = this.messages.map(msg => `
            <div class="message ${msg.sender_id === this.ownerId ? 'my-message' : 'other-message'}">
                <strong>${msg.sender_name}</strong>
                <p>${msg.message}</p>
                <small>${new Date(msg.sent_at).toLocaleString()}</small>
            </div>
        `).join('');
        container.scrollTop = container.scrollHeight;
    }

    renderMembers() {
        const container = document.getElementById('members-container');
        container.innerHTML = `
            <div class="members-list">
                ${this.members.map(m => `
                    <div class="member-item">
                        <p><strong>${m.name}</strong> (${m.role})</p>
                        <small>${m.email}</small>
                        <small>${m.total_properties} properties</small>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

// Usage
const chat = new OwnerCommunityChat(123, 'chat-container');
```

### 4. API Usage with Fetch

```javascript
// Create Community
async function createCommunity(name, description, ownerId) {
    const response = await fetch('/NEWAPI/owner_community/create_community.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: name,
            description: description,
            created_by: ownerId,
            community_type: 'public'
        })
    });
    return await response.json();
}

// Get Communities
async function getCommunities(ownerId) {
    const response = await fetch(`/NEWAPI/owner_community/get_communities.php?owner_id=${ownerId}`);
    return await response.json();
}

// Get Community Members
async function getCommunityMembers(communityId) {
    const response = await fetch(`/NEWAPI/owner_community/get_community_members.php?community_id=${communityId}`);
    return await response.json();
}

// Send Message
async function sendMessage(communityId, senderId, message) {
    const response = await fetch('/NEWAPI/owner_community/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            community_id: communityId,
            sender_id: senderId,
            message: message,
            message_type: 'text'
        })
    });
    return await response.json();
}

// Get Messages
async function getMessages(communityId, ownerId) {
    const response = await fetch(`/NEWAPI/owner_community/get_messages.php?community_id=${communityId}&owner_id=${ownerId}`);
    return await response.json();
}
```

## CSS Styling

```css
.owner-community-chat {
    display: flex;
    height: 600px;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.communities-list {
    width: 250px;
    border-right: 1px solid #ddd;
    overflow-y: auto;
    padding: 10px;
}

.community-item {
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #eee;
    border-radius: 4px;
    cursor: pointer;
}

.community-item.active {
    background: #007bff;
    color: white;
}

.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 15px;
}

.messages {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 10px;
}

.message {
    margin: 10px 0;
    padding: 10px;
    border-radius: 4px;
}

.my-message {
    background: #007bff;
    color: white;
    margin-left: 20%;
}

.other-message {
    background: #f0f0f0;
    margin-right: 20%;
}

.message-input {
    display: flex;
    gap: 10px;
}

.message-input input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.members-list {
    margin-top: 15px;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}

.member-item {
    padding: 8px;
    margin: 5px 0;
    background: #f9f9f9;
    border-radius: 4px;
}
```

---

**Choose your framework and integrate! 🚀**