// Firebase Group Chat Helper
// Real-time group messaging with typing indicators and member management

class FirebaseGroupChat {
    constructor() {
        this.currentUserId = null;
        this.listeners = {};
        this.database = null;
        this.initialized = false;
    }

    // Initialize Firebase (reuse from firebase_realtime_chat.js or pass database instance)
    async init(config) {
        try {
            const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js');
            const { getDatabase, ref, onValue, push, set, update, remove, serverTimestamp } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js');
            
            const app = initializeApp(config);
            this.database = getDatabase(app);
            this.ref = ref;
            this.onValue = onValue;
            this.push = push;
            this.set = set;
            this.update = update;
            this.remove = remove;
            this.serverTimestamp = serverTimestamp;
            this.initialized = true;
            
            console.log('✅ Firebase Group Chat initialized');
            return true;
        } catch (error) {
            console.error('❌ Firebase initialization failed:', error);
            return false;
        }
    }

    // Set current user
    setUser(userId) {
        this.currentUserId = userId;
    }

    // ✅ REAL-TIME: Listen to group messages
    listenToGroupMessages(groupId, callback) {
        if (!this.initialized) {
            console.error('Firebase not initialized');
            return;
        }

        const messagesRef = this.ref(this.database, `groups/${groupId}/messages`);
        
        console.log('🔊 Listening to group:', `groups/${groupId}/messages`);
        
        const unsubscribe = this.onValue(messagesRef, (snapshot) => {
            console.log('📩 Group messages received:', snapshot.val());
            
            const messages = [];
            snapshot.forEach((child) => {
                messages.push({
                    message_id: child.key,
                    ...child.val()
                });
            });
            
            // Sort by timestamp
            messages.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));
            
            callback(messages);
        });

        this.listeners[`group_${groupId}`] = unsubscribe;
        return unsubscribe;
    }

    // Send group message
    async sendGroupMessage(groupId, message, messageType = 'text') {
        if (!this.initialized) {
            console.error('Firebase not initialized');
            return null;
        }

        try {
            // Push directly to Firebase
            const messagesRef = this.ref(this.database, `groups/${groupId}/messages`);
            const newMessageRef = this.push(messagesRef);
            
            const messageData = {
                sender_id: parseInt(this.currentUserId),
                message: message,
                message_type: messageType,
                timestamp: Date.now(),
                read_by: [] // Array of user IDs who read this message
            };
            
            await this.set(newMessageRef, messageData);
            console.log('✅ Group message sent:', newMessageRef.key);
            
            // Update group last activity
            await this.update(this.ref(this.database, `groups/${groupId}`), {
                last_message: message,
                last_message_time: Date.now(),
                last_sender_id: parseInt(this.currentUserId)
            });
            
            // Backup to MySQL
            try {
                const formData = new FormData();
                formData.append('group_id', groupId);
                formData.append('sender_id', this.currentUserId);
                formData.append('message', message);
                formData.append('message_type', messageType);

                await fetch('/NEWAPI/chats/send_community_message.php', {
                    method: 'POST',
                    body: formData
                });
                console.log('✅ Group message backed up to MySQL');
            } catch (error) {
                console.warn('⚠️ MySQL backup failed:', error);
            }
            
            return newMessageRef.key;
        } catch (error) {
            console.error('❌ Error sending group message:', error);
            return null;
        }
    }

    // ✅ REAL-TIME: Listen to typing in group
    listenToGroupTyping(groupId, callback) {
        if (!this.initialized) return;

        const typingRef = this.ref(this.database, `group_typing/${groupId}`);
        return this.onValue(typingRef, (snapshot) => {
            const typingUsers = [];
            const typingData = snapshot.val() || {};
            
            Object.keys(typingData).forEach(userId => {
                if (userId != this.currentUserId && typingData[userId]?.typing) {
                    typingUsers.push({
                        user_id: userId,
                        ...typingData[userId]
                    });
                }
            });
            
            callback(typingUsers);
        });
    }

    // Set typing status in group
    async setGroupTyping(groupId, isTyping) {
        if (!this.initialized) return;

        const typingRef = this.ref(this.database, `group_typing/${groupId}/${this.currentUserId}`);
        
        if (isTyping) {
            await this.set(typingRef, {
                typing: true,
                timestamp: Date.now()
            });
        } else {
            await this.remove(typingRef);
        }
    }

    // ✅ REAL-TIME: Listen to online members
    listenToOnlineMembers(groupId, callback) {
        if (!this.initialized) return;

        const membersRef = this.ref(this.database, `group_members/${groupId}`);
        return this.onValue(membersRef, (snapshot) => {
            const members = [];
            snapshot.forEach((child) => {
                members.push({
                    user_id: child.key,
                    ...child.val()
                });
            });
            callback(members);
        });
    }

    // Set member online status
    async setMemberOnline(groupId, isOnline) {
        if (!this.initialized || !this.currentUserId) return;

        const memberRef = this.ref(this.database, `group_members/${groupId}/${this.currentUserId}`);
        
        await this.set(memberRef, {
            online: isOnline,
            last_seen: Date.now()
        });
    }

    // Mark group message as read
    async markGroupMessageAsRead(groupId, messageId) {
        if (!this.initialized || !this.currentUserId) return;

        try {
            const messageRef = this.ref(this.database, `groups/${groupId}/messages/${messageId}`);
            const snapshot = await this.onValue(messageRef, (snap) => snap.val(), { onlyOnce: true });
            
            const readBy = snapshot.read_by || [];
            if (!readBy.includes(this.currentUserId)) {
                readBy.push(parseInt(this.currentUserId));
                await this.update(messageRef, { read_by: readBy });
            }
        } catch (error) {
            console.error('Error marking message as read:', error);
        }
    }

    // Get group info
    async getGroupInfo(groupId) {
        if (!this.initialized) return null;

        try {
            const groupRef = this.ref(this.database, `groups/${groupId}`);
            const snapshot = await new Promise((resolve) => {
                this.onValue(groupRef, (snap) => resolve(snap), { onlyOnce: true });
            });
            return snapshot.val();
        } catch (error) {
            console.error('Error getting group info:', error);
            return null;
        }
    }

    // Stop listening to specific group
    stopListening(groupId) {
        const key = `group_${groupId}`;
        if (this.listeners[key]) {
            this.listeners[key]();
            delete this.listeners[key];
        }
    }

    // Stop all listeners
    stopAllListeners() {
        Object.keys(this.listeners).forEach(key => {
            this.listeners[key]();
        });
        this.listeners = {};
    }
}

// Export for use in other files
window.FirebaseGroupChat = FirebaseGroupChat;
