// Firebase Real-time Chat Helper (Works with existing sand111 project)
// This uses Firebase Realtime Database for instant updates

class FirebaseRealtimeChat {
    constructor() {
        this.currentUserId = null;
        this.listeners = {};
        this.database = null;
        this.initialized = false;
    }

    // Initialize Firebase (call this first)
    async init(config) {
        try {
            const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js');
            const { getDatabase, ref, onValue, push, set, update, serverTimestamp } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js');
            
            const app = initializeApp(config);
            this.database = getDatabase(app);
            this.ref = ref;
            this.onValue = onValue;
            this.push = push;
            this.set = set;
            this.update = update;
            this.serverTimestamp = serverTimestamp;
            this.initialized = true;
            
            console.log('✅ Firebase initialized successfully');
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

    // ✅ REAL-TIME: Listen to messages (auto-updates without refresh!)
    listenToMessages(chatId, callback) {
        if (!this.initialized) {
            console.error('Firebase not initialized. Call init() first.');
            return;
        }

        const messagesRef = this.ref(this.database, `chats/${chatId}/messages`);
        
        console.log('🔊 Listening to:', `chats/${chatId}/messages`);
        
        const unsubscribe = this.onValue(messagesRef, (snapshot) => {
            console.log('📩 Firebase data received:', snapshot.val());
            
            const messages = [];
            snapshot.forEach((child) => {
                messages.push({
                    message_id: child.key,
                    ...child.val()
                });
            });
            
            console.log('📨 Parsed messages:', messages);
            
            // Sort by timestamp
            messages.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));
            
            callback(messages);
        });

        this.listeners[chatId] = unsubscribe;
        return unsubscribe;
    }

    // Send message (saves to both Firebase and MySQL)
    async sendMessage(chatId, message, messageType = 'text') {
        if (!this.initialized) {
            console.error('Firebase not initialized');
            return null;
        }

        try {
            // Push directly to Firebase first
            const messagesRef = this.ref(this.database, `chats/${chatId}/messages`);
            const newMessageRef = this.push(messagesRef);
            
            const messageData = {
                sender_id: parseInt(this.currentUserId),
                message: message,
                message_type: messageType,
                timestamp: Date.now()
            };
            
            await this.set(newMessageRef, messageData);
            console.log('✅ Message sent to Firebase:', newMessageRef.key);
            
            // Then save to MySQL as backup (optional)
            try {
                const formData = new FormData();
                formData.append('chat_id', chatId);
                formData.append('sender_id', this.currentUserId);
                formData.append('message', message);
                formData.append('message_type', messageType);

                await fetch('/NEWAPI/chats/send_message.php', {
                    method: 'POST',
                    body: formData
                });
                console.log('✅ Message backed up to MySQL');
            } catch (error) {
                console.warn('⚠️ MySQL backup failed (Firebase still working):', error);
            }
            
            return newMessageRef.key;
        } catch (error) {
            console.error('❌ Error sending message:', error);
            return null;
        }
    }

    // ✅ REAL-TIME: Listen to typing indicator
    listenToTyping(chatId, callback) {
        if (!this.initialized) return;

        const typingRef = this.ref(this.database, `typing/${chatId}`);
        return this.onValue(typingRef, (snapshot) => {
            callback(snapshot.val() || {});
        });
    }

    // Set typing status
    async setTyping(chatId, isTyping) {
        if (!this.initialized) return;

        const typingRef = this.ref(this.database, `typing/${chatId}/${this.currentUserId}`);
        
        if (isTyping) {
            await this.set(typingRef, {
                typing: true,
                timestamp: Date.now()
            });
        } else {
            await this.set(typingRef, null);
        }
    }

    // ✅ REAL-TIME: Listen to online users
    listenToOnlineUsers(callback) {
        if (!this.initialized) return;

        const onlineRef = this.ref(this.database, 'online_users');
        return this.onValue(onlineRef, (snapshot) => {
            const users = [];
            snapshot.forEach((child) => {
                users.push({
                    user_id: child.key,
                    ...child.val()
                });
            });
            callback(users);
        });
    }

    // Set user online status
    async setOnlineStatus(isOnline) {
        if (!this.initialized || !this.currentUserId) return;

        const userRef = this.ref(this.database, `online_users/${this.currentUserId}`);
        
        if (isOnline) {
            await this.set(userRef, {
                online: true,
                last_seen: Date.now()
            });
        } else {
            await this.update(userRef, {
                online: false,
                last_seen: Date.now()
            });
        }
    }

    // Stop listening to specific chat
    stopListening(chatId) {
        if (this.listeners[chatId]) {
            this.listeners[chatId]();
            delete this.listeners[chatId];
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
window.FirebaseRealtimeChat = FirebaseRealtimeChat;
