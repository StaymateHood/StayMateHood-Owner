// Firebase configuration and real-time messaging
class OwnerCommunityFirebase {
    constructor(firebaseConfig) {
        // Initialize Firebase
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }
        this.database = firebase.database();
        this.currentCommunityId = null;
        this.messagesListener = null;
    }

    // Listen to real-time messages
    listenToMessages(communityId, callback) {
        if (this.messagesListener) {
            this.messagesListener.off();
        }
        
        this.currentCommunityId = communityId;
        const messagesRef = this.database.ref(`owner_communities/${communityId}/messages`);
        
        this.messagesListener = messagesRef.orderByChild('timestamp').limitToLast(50);
        this.messagesListener.on('value', (snapshot) => {
            const messages = [];
            snapshot.forEach((childSnapshot) => {
                const message = childSnapshot.val();
                message.firebase_key = childSnapshot.key;
                messages.push(message);
            });
            callback(messages);
        });
    }

    // Send message to Firebase
    async sendMessage(communityId, messageData) {
        const messagesRef = this.database.ref(`owner_communities/${communityId}/messages`);
        
        try {
            const result = await messagesRef.push(messageData);
            
            // Update last message
            await this.database.ref(`owner_communities/${communityId}/last_message`).set({
                message: messageData.message,
                timestamp: messageData.timestamp,
                sender_name: messageData.sender_name
            });
            
            return { success: true, key: result.key };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    // Stop listening to messages
    stopListening() {
        if (this.messagesListener) {
            this.messagesListener.off();
            this.messagesListener = null;
        }
    }

    // Delete message
    async deleteMessage(communityId, messageKey) {
        try {
            await this.database.ref(`owner_communities/${communityId}/messages/${messageKey}`).update({
                is_deleted: true,
                deleted_at: Date.now()
            });
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    // Get community info
    async getCommunityInfo(communityId) {
        try {
            const snapshot = await this.database.ref(`owner_communities/${communityId}`).once('value');
            return { success: true, data: snapshot.val() };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
}

// Usage example:
/*
const firebaseConfig = {
    apiKey: "your-api-key",
    authDomain: "your-project.firebaseapp.com",
    databaseURL: "https://your-project-default-rtdb.firebaseio.com",
    projectId: "your-project-id",
    storageBucket: "your-project.appspot.com",
    messagingSenderId: "123456789",
    appId: "your-app-id"
};

const ownerCommunityFirebase = new OwnerCommunityFirebase(firebaseConfig);

// Listen to messages
ownerCommunityFirebase.listenToMessages(communityId, (messages) => {
    displayMessages(messages);
});

// Send message
const messageData = {
    community_id: communityId,
    sender_id: senderId,
    sender_name: senderName,
    sender_image: senderImage,
    message: messageText,
    message_type: 'text',
    timestamp: Date.now(),
    sent_at: new Date().toISOString(),
    is_deleted: false
};

ownerCommunityFirebase.sendMessage(communityId, messageData);
*/